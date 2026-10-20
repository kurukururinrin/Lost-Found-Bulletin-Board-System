# "flask" create server, "request" receive images, "jsonify" convert py result to json
from flask import Flask, request, jsonify
from transformers import CLIPProcessor, CLIPModel
# PIL is used to read and process the images
from PIL import Image
from io import BytesIO
import os
# help ai to do mathematical calculation
import torch
# provide commonly used function for neural network
import torch.nn.functional as F
import mysql.connector

# create app
app = Flask(__name__)

print("Loading CLIP model...")

# the model to analyze (responsible to convert image to embedding num)
model = CLIPModel.from_pretrained("openai/clip-vit-base-patch32")
# to prepare the images (convert the image to filetype that clip can accept)
processor = CLIPProcessor.from_pretrained("openai/clip-vit-base-patch32")

print("CLIP model loaded!")

# convert images to embedding num, which clip can understand
def get_image_embedding(image_path):
    # open image path
    image = Image.open(image_path)
    # tensor is container that store embedding codes / feature vector
    inputs = processor(images=image, return_tensors="pt")
    # where analyzing start
    outputs = model.get_image_features(**inputs)

    # 兼容不同形式的model output
    if hasattr(outputs, "pooler_output"):
        embedding = outputs.pooler_output
    else:
        embedding = outputs

    # normalize embedding 标准化格式，方便对比
    embedding = F.normalize(embedding, p=2, dim=-1)
    return embedding


# Connect to MySQL database
db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="itmp"
)

cursor = db.cursor()

cursor.execute("""
    SELECT Item_ID, ItemName, ItemImage, ItemDescription
    FROM item
    WHERE ItemStatus IN ('Found', 'Available')
""")

items = cursor.fetchall()
cursor.close()
db.close()


embedding_dir = "embeddings"

if not os.path.exists(embedding_dir):
    os.makedirs(embedding_dir)

candidate_embeddings = {}

print("Loading candidate embeddings...")
for item_id, item_name, image_data, item_description in items:
    cache_path = os.path.join(embedding_dir,f"item_{item_id}.pt")

    if os.path.exists(cache_path):
        print(f"Loading cached embedding: {item_name}")

        candidate_embedding = torch.load(cache_path,weights_only=True)
    else:
        print(f"Creating embedding: {item_name}")

        image = Image.open(BytesIO(image_data))
        inputs = processor(images=image,return_tensors="pt")
        outputs = model.get_image_features(**inputs)

        if hasattr(outputs, "pooler_output"):
            candidate_embedding = outputs.pooler_output
        else:
            candidate_embedding = outputs

        candidate_embedding = F.normalize(candidate_embedding,p=2,dim=-1)
        torch.save(candidate_embedding, cache_path)

    candidate_embeddings[item_id] = {
    "name": item_name,
    "description": item_description,
    "embedding": candidate_embedding
    }


def get_text_embedding(text):
    inputs = processor(
        text=text,
        return_tensors="pt",
        padding=True
    )

    outputs = model.get_text_features(**inputs)

    if hasattr(outputs, "pooler_output"):
        embedding = outputs.pooler_output
    else:
        embedding = outputs

    embedding = F.normalize(embedding, p=2, dim=-1)

    return embedding


# if someone send POST request to match.php, execute below
@app.route("/match", methods=["POST"])
def match_image():

    image = request.files.get("image")
    description = request.form.get("description", "")

    # Check whether user provided at least one input
    if image is None and description.strip() == "":
        return jsonify({
            "error": "Please provide an image or description"
        }), 400

    # Image embedding
    lost_embedding = None
    temp_path = "temp_uploaded_image.jpg"

    if image is not None:
        image.save(temp_path)
        lost_embedding = get_image_embedding(temp_path)

    # Description embedding
    description_embedding = None

    if description.strip() != "":
        description_embedding = get_text_embedding(description)

    # Store results separately
    image_results = []
    description_results = []

    for item_id, candidate in candidate_embeddings.items():

        # Image similarity
        if lost_embedding is not None:

            image_similarity = torch.sum(
                lost_embedding * candidate["embedding"],
                dim=-1
            ).item()

            image_results.append({
                "item_id": item_id,
                "item_name": candidate["name"],
                "similarity": round(image_similarity * 100, 2)
            })

        # Description similarity
        if description_embedding is not None and candidate["description"]:

            candidate_description_embedding = get_text_embedding(
                candidate["description"]
            )

            description_similarity = torch.sum(
                description_embedding * candidate_description_embedding,
                dim=-1
            ).item()

            description_results.append({
                "item_id": item_id,
                "item_name": candidate["name"],
                "similarity": round(description_similarity * 100, 2)
            })

    # Sort image results
    image_results.sort(
        key=lambda x: x["similarity"],
        reverse=True
    )

    # Sort description results
    description_results.sort(
        key=lambda x: x["similarity"],
        reverse=True
    )

    # Remove temp image only if an image was uploaded
    if image is not None:
        os.remove(temp_path)

    # Send results to PHP
    return jsonify({
        "image_matches": image_results,
        "description_matches": description_results
    })


@app.route("/verify_claim", methods=["POST"])
def verify_claim():

    data = request.get_json()

    category = data.get("category", "").strip()
    description = data.get("description", "").strip()
    item_category = data.get("item_category", "").strip()
    item_description = data.get("item_description", "").strip()

    if not category or not description:
        return jsonify({
            "error": "Claim information is incomplete."
        }), 400

    if not item_category or not item_description:
        return jsonify({
            "error": "Item information is incomplete."
        }), 400

    claim_text = category + ". " + description
    item_text = item_category + ". " + item_description

    claim_embedding = get_text_embedding(claim_text)
    item_embedding = get_text_embedding(item_text)

    similarity = torch.cosine_similarity(
        claim_embedding,
        item_embedding
    ).item()

    similarity_percentage = round(
        similarity * 100,
        2
    )

    print("Claim similarity:", similarity_percentage)

    return jsonify({
        "similarity": similarity_percentage
    })


# if the file is processing
if __name__ == "__main__":
        app.run(host="127.0.0.1", port=5000)