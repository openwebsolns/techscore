#!/bin/bash -e

# find the app version
version=$(grep APP_VERSION lib/conf.php | cut -d"'" -f 2)

echo "→ Using version '$version'"

echo "→ Logging in"
aws ecr-public get-login-password --region us-east-1 | docker login --username AWS --password-stdin public.ecr.aws/q8i6q7k3

echo "→ Building"
docker build -t openwebsolns/techscore .

echo "→ Tagging"
docker tag openwebsolns/techscore:latest public.ecr.aws/q8i6q7k3/techscore:$version
docker tag openwebsolns/techscore:latest public.ecr.aws/q8i6q7k3/techscore:latest

echo "→ Pushing"
docker push public.ecr.aws/q8i6q7k3/techscore:$version
docker push public.ecr.aws/q8i6q7k3/techscore:latest
