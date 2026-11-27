FROM node:22.23.2-alpine@sha256:b6f26b36c8ff49624cfdac716b8ea1138d606df02586a77d364bb5536a634f85 AS build
WORKDIR /ui
COPY --from=frontend package.json package-lock.json ./
RUN npm ci
COPY --from=frontend . .
RUN npm run build

FROM node:22.23.2-alpine@sha256:b6f26b36c8ff49624cfdac716b8ea1138d606df02586a77d364bb5536a634f85
WORKDIR /ui
COPY --from=build /ui/node_modules ./node_modules
COPY --from=build /ui/dist ./dist
ENV NODE_ENV=production
CMD ["node", "dist/frontend/server/server.mjs"]
