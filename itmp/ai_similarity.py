from transformers import CLIPProcessor, CLIPModel
from PIL import Image
import os
import sys
import torch
import torch.nn.functional as F

print("Loading CLIP model...")

model = CLIPModel.from_pretrained("openai/clip-vit-base-patch32")
processor = CLIPProcessor.from_pretrained("openai/clip-vit-base-patch32")


def get_image_embedding(image_path):
    image = Image.open(image_path)
    inputs = processor(images=image,return_tensors="pt")
    outputs = model.get_image_features(**inputs)

    if hasattr(outputs, "pooler_output"):
        embedding = outputs.pooler_output
    else:
        embedding = outputs

    embedding = F.normalize(embedding, p=2, dim=-1)
    return embedding

# Get image path from PHP
if len(sys.argv) < 2:
    print("ERROR: No image path provided.")
    exit()

lost_image = sys.argv[1]
# Get embedding of lost item
lost_embedding = get_image_embedding(lost_image)

# Test candidate images
candidate_images = [
    "uploads/black_wallet.jpg",
    "uploads/black_jacket.jpg",
    "uploads/phone.jpg",
    "uploads/blue_notebook.jpg",
    "uploads/student_card.jpg",
    "uploads/black_water_bottle.jpg"
]

# Create embedding folder
embedding_dir = "embeddings"

if not os.path.exists(embedding_dir):
    os.makedirs(embedding_dir)

results = []

for image_path in candidate_images:

    if not os.path.exists(image_path):
        continue

    # Create cache file name
    file_name = os.path.basename(image_path)
    cache_name = os.path.splitext(file_name)[0] + ".pt"
    cache_path = os.path.join(embedding_dir, cache_name)

    #check if embedding exists
    if os.path.exists(cache_path):
        print(f"Loading cached embedding: {file_name}")

        candidate_embedding = torch.load(
            cache_path,
            weights_only=True
        )

    else:
        print(f"Creating embedding: {file_name}")
        candidate_embedding = get_image_embedding(image_path)

        torch.save(
            candidate_embedding, cache_path
        )

    # Calculate cosine similarity
    similarity = torch.sum(
        lost_embedding * candidate_embedding,
        dim=-1
    ).item()

    results.append((image_path, similarity))

# Sort from highest similarity to lowest
results.sort(key=lambda x: x[1], reverse=True)

print("\nPotential Matches:")

for image_path, similarity in results:
    percentage = similarity * 100
    print(
        f"{os.path.basename(image_path)} - "
        f"Similarity: {percentage:.2f}%"
    )