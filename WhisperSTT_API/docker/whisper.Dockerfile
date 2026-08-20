# Thin layer over the official whisper.cpp image that bakes in our entrypoint
# (downloads the model on first boot, then runs whisper-server). Baking it in
# avoids fragile host bind-mounts that break on managed deploy platforms.
FROM ghcr.io/ggml-org/whisper.cpp:main

COPY docker/whisper-entrypoint.sh /entrypoint/whisper-entrypoint.sh
RUN chmod +x /entrypoint/whisper-entrypoint.sh

ENTRYPOINT ["/bin/sh", "/entrypoint/whisper-entrypoint.sh"]
