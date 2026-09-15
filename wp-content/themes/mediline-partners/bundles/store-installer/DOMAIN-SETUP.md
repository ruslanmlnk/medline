The generated package configures the supplied hostname automatically using Caddy,
WordPress and Docker Compose. No manual nginx or Apache virtual host is needed on
a dedicated server with ports 80 and 443 available.

Public domains use HTTPS with automatic certificate issuance and renewal. A leading
`www.` entered in Store Builder is removed. HTTP and HTTPS requests to `www` receive
a permanent 301 redirect to the HTTPS hostname without `www`; paths and query strings
are preserved. Localhost and IPv4 installations keep HTTP without a www alias.

Before running the installation command, point DNS for the hostname and its www
alias to the destination server. DNS changes require access to the DNS provider;
generating or downloading a ZIP cannot make those changes. Permit incoming ports
80 and 443. Do not run this standalone Compose stack on ports already owned by
nginx, Apache, Traefik or another store. Use that server's deployment platform to
attach both hostnames, TLS and the redirect instead; the installer does not stop
existing websites or overwrite their configuration.

Additional languages are optional. With no additional languages selected, only
the primary language is provisioned and the storefront hides the language switcher.
