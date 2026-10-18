# "flask" creates the server, "request" receives images, "jsonify" converts results to JSON
from flask import Flask, request, jsonify
from transformers import CLIPProcessor, CLIPModel
# PIL is used to read and process images
from PIL import Image
from io import BytesIO
import os

# Help AI perform mathematical calculations
import torch
# Provide commonly used functions for neural networks
import torch.nn.functional as F
import mysql.connector

# Create app
app = Flask(__name__)

# Get the folder where this Python file is located
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

print("Loading CLIP model...")

# The model converts images and text into embeddings
model = CLIPModel.from_pretrained("openai/clip-vit-base-patch32")
processor = CLIPProcessor.from_pretrained("openai/clip-vit-base-patch32")

print("CLIP model loaded!")


# Convert an image into an embedding
def get_image_embedding(image):
    # Accept either a PIL image or an image file
    if not isinstance(image, Image.Image):
        image = Image.open(image)

    # Convert the image to RGB
    image = image.convert("RGB")

    # Prepare the image for CLIP
    inputs = processor(images=image, return_tensors="pt")
    outputs = model.get_image_features(**inputs)

    # Support different model output formats
    if hasattr(outputs, "pooler_output"):
        embedding = outputs.pooler_output
    else:
        embedding = outputs

    # Normalize the embedding for comparison
    embedding = F.normalize(embedding, p=2, dim=-1)
    return embedding


# Connect to MySQL database
# DB_SSL_CA must point to the CA certificate file available on the server
ssl_ca_path = os.environ.get(
    "DB_SSL_CA",
    os.path.join(BASE_DIR, "ca.pem")
)

conn = mysql.connector.connect(
    host=os.environ["DB_HOST"],
    port=int(os.environ.get("DB_PORT", "28583")),
    user=os.environ["DB_USER"],
    password=os.environ["DB_PASSWORD"],
    database=os.environ["DB_NAME"],
    ssl_ca=ssl_ca_path,
    ssl_verify_cert=True,
    ssl_verify_identity=True
)

try:
    cursor = conn.cursor()

    cursor.execute("""
        SELECT Item_ID, ItemName, ItemImage, ItemDescription
        FROM item
        WHERE ItemStatus IN ('Found', 'Available')
    """)

    items = cursor.fetchall()

finally:
    cursor.close()
    conn.close()


# Folder for cached image embeddings
embedding_dir = os.path.join(BASE_DIR, "embeddings")
os.makedirs(embedding_dir, exist_ok=True)

candidate_embeddings = {}

print("Loading candidate embeddings...")

for item_id, item_name, image_data, item_description in items:

    cache_path = os.path.join(
        embedding_dir,
        f"item_{item_id}.pt"
    )

    candidate_embedding = None

    # Only process an image if the item has one
    if image_data is not None:

        if os.path.exists(cache_path):
            print(f"Loading cached embedding: {item_name}")

            candidate_embedding = torch.load(
                cache_path,
                map_location="cpu",
                weights_only=True
            )

        else:
            print(f"Creating embedding: {item_name}")

            image = Image.open(BytesIO(image_data)).convert("RGB")
            inputs = processor(images=image, return_tensors="pt")
            outputs = model.get_image_features(**inputs)

            if hasattr(outputs, "pooler_output"):
                candidate_embedding = outputs.pooler_output
            else:
                candidate_embedding = outputs

            candidate_embedding = F.normalize(
                candidate_embedding,
                p=2,
                dim=-1
            )

            torch.save(candidate_embedding, cache_path)

    else:
        print(f"No image available for: {item_name}")

    # Keep the item even if it has no image.
    # It can still be used for description matching.
    candidate_embeddings[item_id] = {
        "name": item_name,
        "description": item_description,
        "embedding": candidate_embedding
    }

print(f"Loaded {len(candidate_embeddings)} candidate items.")


# Convert text into an embedding
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


# Execute this function when PHP sends a POST request to /match
@app.route("/match", methods=["POST"])
def match_image():

    image = request.files.get("image")
    description = request.form.get("description", "").strip()

    # Check whether the user provided at least one input
    if (image is None or image.filename == "") and description == "":
        return jsonify({
            "error": "Please provide an image or description"
        }), 400

    # Image embedding
    lost_embedding = None

    if image is not None and image.filename != "":
        uploaded_image = Image.open(image.stream).convert("RGB")
        lost_embedding = get_image_embedding(uploaded_image)

    # Description embedding
    description_embedding = None

    if description != "":
        description_embedding = get_text_embedding(description)

    # Store results separately
    image_results = []
    description_results = []

    for item_id, candidate in candidate_embeddings.items():

        # Image similarity
        # Skip items without an image embedding
        if (
            lost_embedding is not None
            and candidate["embedding"] is not None
        ):
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
        if (
            description_embedding is not None
            and candidate["description"]
        ):
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
                "similarity": round(
                    description_similarity * 100,
                    2
                )
            })

    # Sort image results by similarity
    image_results.sort(
        key=lambda x: x["similarity"],
        reverse=True
    )

    # Sort description results by similarity
    description_results.sort(
        key=lambda x: x["similarity"],
        reverse=True
    )

    # Send results to PHP
    return jsonify({
        "image_matches": image_results,
        "description_matches": description_results
    })


@app.route("/verify_claim", methods=["POST"])
def verify_claim():

    data = request.get_json(silent=True) or {}

    category = str(data.get("category") or "").strip()
    description = str(data.get("description") or "").strip()
    item_category = str(data.get("item_category") or "").strip()
    item_description = str(data.get("item_description") or "").strip()

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

    similarity_percentage = round(similarity * 100, 2)

    print("Claim similarity:", similarity_percentage)

    return jsonify({
        "similarity": similarity_percentage
    })


# Start the server
if __name__ == "__main__":
    app.run(
        host="0.0.0.0",
        port=int(os.environ.get("PORT", 5000))
    )
