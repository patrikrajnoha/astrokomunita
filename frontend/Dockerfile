# ---- Build stage ----
FROM node:20-alpine AS build
WORKDIR /app

COPY package*.json ./
RUN npm ci --frozen-lockfile

# Vite bakes these into the JS bundle at build time
ARG VITE_API_BASE_URL
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_APP_NAME=Astrokomunita
ARG VITE_LEGAL_CONTROLLER_NAME
ARG VITE_LEGAL_CONTACT_EMAIL
ARG VITE_SESSION_LIFETIME_HOURS=168
ARG VITE_LOG_RETENTION_DAYS=30
ARG VITE_NOTIFICATION_RETENTION_DAYS=90
ARG VITE_SENTRY_DSN
ARG VITE_SENTRY_ENV=production
ARG VITE_SENTRY_RELEASE

COPY . .
RUN npm run build

# ---- Serve stage ----
FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
