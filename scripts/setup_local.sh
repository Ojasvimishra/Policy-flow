#!/usr/bin/env bash
set -e

echo "=========================================================="
echo " PolicyFlow - Local Environment Setup Verification"
echo "=========================================================="

# Check for .env
if [ ! -f .env ]; then
    echo "[*] Creating .env from .env.example..."
    cp .env.example .env
else
    echo "[✓] .env file already exists."
fi

# Tool checks
echo -e "\n[*] Checking required tools:"
for tool in php composer docker redis-cli node npm; do
    if command -v $tool >/dev/null 2>&1; then
        echo "  [✓] $tool is available: $(command -v $tool)"
    else
        echo "  [!] $tool is NOT installed in PATH."
    fi
done

echo -e "\n[*] Docker Compose verification:"
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    echo "  [✓] Docker Compose is ready. Run 'docker compose up -d' to start."
else
    echo "  [!] Docker Compose is not installed. Use native setup or install Docker."
fi

echo -e "\n=========================================================="
echo " Setup check complete."
echo "=========================================================="
