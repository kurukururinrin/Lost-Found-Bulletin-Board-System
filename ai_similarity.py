from transformers import CLIPProcessor, CLIPModel
from PIL import Image
import os
import sys
import torch
import torch.nn.functional as F

print("Loading CLIP model...")

# Load the pre-trained CLIP model and processor.
# CLIP converts images and text into embeddings (number vectors) for comparison.
model = CLIPModel.from_pretrained("openai/clip-vit-base-patch32")
processor = CLIPProcessor.from_pretrained("openai/clip-vit-base-patch32")


def get_image_embedding(image_path):
    # Open the image from the given file path.
    image = Image.open(image_path)

    # Prepare the image for CLIP (resize, normalize, convert to tensor).
    inputs = processor(images=image,return_tensors="pt")

    # Run the image through CLIP to get its feature vector.
    outputs = model.get_image_features(**inputs)

    # Extract the embedding from the model output.
    # (Some versions return an object with .pooler_output, others return the tensor directly.)
    if hasattr(outputs, "pooler_output"):
        embedding = outputs.pooler_output
    else:
        embedding = outputs

    # Normalize the embedding so similarity comparisons are fair.
    embedding = F.normalize(embedding, p=2, dim=-1)
    return embedding

# Get image path from PHP (passed as a command-line argument)
if len(sys.argv) < 2:
    print("ERROR: No image path provided.")
    exit()

# Read the path of the lost item image.
lost_image = sys.argv[1]
# Get embedding of lost item
lost_embedding = get_image_embedding(lost_image)

# Test candidate images (these are the "found" items in the system)
candidate_images = [
    "uploads/black_wallet.jpg",
    "uploads/black_jacket.jpg",
    "uploads/phone.jpg",
    "uploads/blue_notebook.jpg",
    "uploads/student_card.jpg",
    "uploads/black_water_bottle.jpg"
]

# Create embedding folder (where we cache embeddings to speed up future runs)
embedding_dir = "embeddings"

if not os.path.exists(embedding_dir):
    os.makedirs(embedding_dir)

# Store results as (image_path, similarity_score) pairs.
results = []

for image_path in candidate_images:

    # Skip files that do not exist.
    if not os.path.exists(image_path):
        continue

    # Create cache file name (e.g. "black_wallet.pt")
    file_name = os.path.basename(image_path)
    cache_name = os.path.splitext(file_name)[0] + ".pt"
    cache_path = os.path.join(embedding_dir, cache_name)

    #check if embedding exists
    if os.path.exists(cache_path):
        print(f"Loading cached embedding: {file_name}")

        # Load the pre-computed embedding from disk.
        candidate_embedding = torch.load(
            cache_path,
            weights_only=True
        )

    else:
        print(f"Creating embedding: {file_name}")
        # Compute the embedding for the first time.
        candidate_embedding = get_image_embedding(image_path)

        # Save it to disk so next time we can just load it.
        torch.save(
            candidate_embedding, cache_path
        )

    # Calculate cosine similarity between the lost item and the candidate.
    similarity = torch.sum(
        lost_embedding * candidate_embedding,
        dim=-1
    ).item()

    # Store the result.
    results.append((image_path, similarity))

# Sort from highest similarity to lowest
results.sort(key=lambda x: x[1], reverse=True)

print("\nPotential Matches:")

# Print each match as "filename - Similarity: 95.00%"
for image_path, similarity in results:
    percentage = similarity * 100
    print(
        f"{os.path.basename(image_path)} - "
        f"Similarity: {percentage:.2f}%"
    )