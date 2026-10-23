# syntax=docker/dockerfile:1

# ==============================================================================
# Stage 1: Node.js 22 Build Stage
# ==============================================================================
FROM node:22-alpine AS builder

WORKDIR /app

# Copy dependency manifests
COPY package.json package-lock.json ./

# Install exact dependencies
RUN npm ci

# Copy frontend source files
COPY . .

# Build argument for API base URL (defaults to relative /api for reverse-proxied deployment)
ARG VITE_API_BASE_URL=/api
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}

# Compile TypeScript and build production bundle
RUN npm run build

# ==============================================================================
# Stage 2: Production Nginx Runtime Stage
# ==============================================================================
FROM nginx:alpine AS runtime

LABEL maintainer="PolicyFlow DevOps Team <devops@trippersshield.internal>"
LABEL description="PolicyFlow Production Frontend SPA (React + Nginx Alpine)"

# Copy custom Nginx SPA configuration
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copy compiled static assets from builder stage
COPY --from=builder /app/dist /usr/share/nginx/html

# Expose port 80
EXPOSE 80

# Health check to ensure Nginx is serving traffic
HEALTHCHECK --interval=20s --timeout=5s --start-period=5s --retries=3 \
    CMD wget -q -O /dev/null http://localhost/healthz || exit 1

CMD ["nginx", "-g", "daemon off;"]
