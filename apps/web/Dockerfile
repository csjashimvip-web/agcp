FROM node:22-alpine AS base
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1

FROM base AS dependencies
COPY package.json ./
RUN npm install \
    && sha256sum package.json | cut -d' ' -f1 > node_modules/.agcp-dependency-hash

FROM base AS development
COPY --from=dependencies /app/node_modules /opt/agcp/node_modules
COPY docker/entrypoint.sh /usr/local/bin/agcp-web-entrypoint
RUN chmod +x /usr/local/bin/agcp-web-entrypoint
ENTRYPOINT ["agcp-web-entrypoint"]
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0"]

FROM base AS builder
ARG NEXT_PUBLIC_APP_NAME="Araabi Global Commerce Platform"
ARG NEXT_PUBLIC_API_BASE_URL="/api/v1"
ENV NEXT_PUBLIC_APP_NAME=$NEXT_PUBLIC_APP_NAME NEXT_PUBLIC_API_BASE_URL=$NEXT_PUBLIC_API_BASE_URL
COPY --from=dependencies /app/node_modules ./node_modules
COPY . .
RUN npm run build

FROM node:22-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 PORT=3000 HOSTNAME=0.0.0.0
RUN addgroup --system --gid 1001 nodejs && adduser --system --uid 1001 nextjs
COPY --from=builder /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static
USER nextjs
EXPOSE 3000
CMD ["node", "server.js"]
