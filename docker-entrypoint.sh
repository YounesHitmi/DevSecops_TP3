#!/bin/sh
set -e

if [ -n "$INFISICAL_CLIENT_ID" ] && [ -n "$INFISICAL_CLIENT_SECRET" ] && [ -n "$INFISICAL_PROJECT_ID" ]; then
    echo "[entrypoint] Fetching secrets from Infisical (env: ${INFISICAL_ENV:-dev})"

    INFISICAL_TOKEN_VALUE=$(infisical login \
        --method=universal-auth \
        --client-id="$INFISICAL_CLIENT_ID" \
        --client-secret="$INFISICAL_CLIENT_SECRET" \
        --silent --plain)

    # The client secret is not needed anymore: do not pass it to the application.
    unset INFISICAL_CLIENT_ID INFISICAL_CLIENT_SECRET

    exec infisical run \
        --token="$INFISICAL_TOKEN_VALUE" \
        --projectId="$INFISICAL_PROJECT_ID" \
        --env="${INFISICAL_ENV:-dev}" \
        -- "$@"
fi

echo "[entrypoint] No Infisical credentials, starting without Infisical"
exec "$@"
