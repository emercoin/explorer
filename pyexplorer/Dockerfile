# syntax=docker/dockerfile:1
#
# One image for both long-running roles; the command picks the role:
#   emcexplorer initdb | emcexplorer index | emcexplorer api --host 0.0.0.0

# --- build: resolve the locked dependencies into /app/.venv with uv -----------
FROM python:3.13-slim AS build
ARG UV_VERSION=0.12.21
ENV UV_COMPILE_BYTECODE=1 \
    UV_LINK_MODE=copy \
    UV_PYTHON_DOWNLOADS=never \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1
RUN pip install "uv==${UV_VERSION}"
WORKDIR /app
# Dependencies first, so code changes do not invalidate this layer.
COPY pyproject.toml uv.lock ./
RUN uv sync --frozen --no-dev --no-install-project
COPY README.md ./
COPY src ./src
RUN uv sync --frozen --no-dev --no-editable

# --- runtime: the venv only, no uv / build tooling ----------------------------
FROM python:3.13-slim
LABEL org.opencontainers.image.title="Emercoin explorer" \
      org.opencontainers.image.description="Emercoin block explorer: indexer + HTTP API" \
      org.opencontainers.image.source="https://github.com/emercoin/explorer"
ENV PATH=/app/.venv/bin:$PATH \
    PYTHONUNBUFFERED=1
RUN useradd --system --uid 10001 --no-create-home explorer
COPY --from=build /app/.venv /app/.venv
USER explorer
EXPOSE 8000
ENTRYPOINT ["emcexplorer"]
CMD ["api", "--host", "0.0.0.0", "--port", "8000"]
