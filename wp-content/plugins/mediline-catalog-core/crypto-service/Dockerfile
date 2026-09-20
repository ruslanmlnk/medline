FROM node:22-bookworm-slim AS watcher
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts && npm cache clean --force
COPY wallet.mjs ledger.mjs providers.mjs server.mjs ./
USER node
ENV HOST=0.0.0.0 PORT=8787 MEDILINE_CRYPTO_DB=/data/payments.sqlite MEDILINE_CRYPTO_CONFIG=/run/secrets/watcher.json
CMD ["node", "server.mjs"]

FROM watcher AS signer
USER root
RUN apt-get update && apt-get install -y --no-install-recommends util-linux && rm -rf /var/lib/apt/lists/*
COPY signer/package.json signer/package-lock.json ./signer/
RUN cd signer && npm ci --omit=dev --ignore-scripts && npm cache clean --force
COPY signer/*.mjs ./signer/
USER node
ENV MEDILINE_SIGNER_DB=/data/signer.sqlite MEDILINE_SIGNER_CONFIG=/run/secrets/signer.json MEDILINE_SIGNER_LOCKED=1
CMD ["flock", "--nonblock", "/data/signer.lock", "node", "signer/worker.mjs"]
