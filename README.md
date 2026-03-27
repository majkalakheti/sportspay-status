# PHP Server Monitor Dashboard

A simple PHP + Bootstrap web app that checks hardcoded server URLs on page load or manual trigger and shows status colors:

- Green: HTTP 200 and `repl: true`
- Orange: HTTP 200 and `repl: false`
- Red: non-200 or no response/error

## Requirements

- PHP 8+ with cURL extension enabled

## Run

```bash
php -S localhost:8000
```

Open: <http://localhost:8000>

## Server list

Server entries are hardcoded in `config.php` (no configuration page).
Each base URL is checked using the rules in `checker.php` (`svra/svrb` use `:1443/Health/check`, others use `/api/Health/check`).
Latest check results are stored in `data/status.json`.

## Notes

- The dashboard runs one check on page load and can trigger checks manually with **Check Now**.
- SSL verification is currently disabled in checks to avoid local issuer certificate errors on endpoints with incomplete/self-signed certificate chains.
- Email notifications are disabled.
- Alert duration is tracked in each status result (`issue_active_since`, `issue_duration_seconds`).
- Resolved incidents are appended to `data/incidents.log` with total duration.

