# FullTime Arena Plain PHP API

This API is intentionally plain PHP for shared hosting. It does not need Laravel, Composer, Artisan, or terminal access.

Upload the API package contents to the document root of:

```text
api.fulltimearena.com
```

Required files in the API document root:

```text
.htaccess
index.php
config.php
src/bootstrap.php
public/.htaccess
public/index.php
public/assets/main.css
public/assets/main.js
```

Configure database values in `config.php`, or create a `.env` file using `.env.example`.

`public/assets/main.css` and `public/assets/main.js` are snapshots of the old PHP UI assets so the SPA panels can keep using the same design language while the API remains plain PHP.

Health checks after upload:

```bash
curl -i https://api.fulltimearena.com/up
curl -i https://api.fulltimearena.com/api/public/bootstrap
curl -i -X OPTIONS https://api.fulltimearena.com/api/user/auth/login \
  -H "Origin: https://fulltimearena.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: content-type,authorization"
```

Expected:

- `/up` returns JSON with `mode: "plain-php"`.
- `/api/public/bootstrap` returns site bootstrap data.
- `OPTIONS` returns `204` with `Access-Control-Allow-Origin`.
