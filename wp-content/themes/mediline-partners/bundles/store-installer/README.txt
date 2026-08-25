MEDILINE STORE INSTALLER

1. Point the selected domain DNS A/AAAA record to this server.
2. Install Docker Engine and Docker Compose.
3. Extract this package.
4. Run:

   sudo ./install.sh

No WordPress/PHP/MySQL installation is required on the host. The installer claims the one-time Mediline configuration, starts WordPress + MariaDB + Caddy, installs the selected storefront theme and Mediline Store Core, configures the catalog credentials, performs a full catalog sync and enables automatic HTTPS.

Useful commands:
  ./status.sh
  ./update.sh
  docker compose logs -f

The one-time provisioning payload is removed after successful installation. Database credentials remain in .env and must not be published.
