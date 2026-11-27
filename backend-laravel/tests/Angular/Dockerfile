FROM mcr.microsoft.com/playwright:v1.64.1-noble@sha256:2f05f8a734509a7b3f26a912639488ee08f58a7b292d14d62271bcff9038768f
RUN apt-get update && apt-get install -y --no-install-recommends libnss3-tools && rm -rf /var/lib/apt/lists/*
WORKDIR /tests
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=auth-fixtures java-bcrypt.json site-content.json ./
CMD ["sh", "run.sh"]
