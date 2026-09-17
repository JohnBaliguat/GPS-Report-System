# FleetIQ — Driver Behavior & Performance System

A web app for **Panabo Trucking Services** that imports **Geotab / MyGeotab `.xlsx`
exports**, normalizes them into a single driver-event model, and visualizes driver
**performance and behavior** with a scoring engine.

Built with **PHP 8 · MySQL (MariaDB) · Bootstrap 5 · jQuery · AJAX · DataTables
(server-side) · Chart.js** — runs on **XAMPP** with **zero external PHP libraries**
(the `.xlsx` parser is pure `ZipArchive` + `SimpleXML`).

---

## What it does

| Feature | Description |
|---|---|
| **Universal importer** | Drag-and-drop one or many `.xlsx` files. The report type is auto-detected — no manual mapping. Duplicate files are skipped (SHA-1 hash). |
| **Report types** | Restricted-zone visits, No-Parking zones, Overstaying, Speeding (per-event), Idling, Geofence visits, Stops/Parkings, POI visits, **Trip / Trip Daily** (movement), and **Advanced Exceptions Detail** (per-exception rows with driver name + GPS lat/lng). |
| **Behavior scoring** | Every event earns *demerit points* by severity. Each driver gets a 0–100 **Behavior Score** and an **A–F grade**. |
| **Dashboard** | KPI cards, daily violation/idling trend, event-mix doughnut, highest-risk-driver bar (click a bar to drill in). |
| **Driver Scorecards** | Ranked table with score ring, grade, per-category counts; click any driver for a breakdown modal. |
| **Event Explorer** | Server-side DataTable over every event (fast even with 100k+ rows), with type/date filters and search. |
| **Import manager** | History of every import; delete an import to remove its events (orphan drivers auto-pruned). |
| **Authentication** | Session-based login. All pages and APIs are guarded (pages redirect to `login.php`, APIs return 401/403). |
| **User management** | Admin-only CRUD for console users with roles (**admin / manager / viewer**) and active/inactive status. |
| **Profile** | Every user can edit their own name/email/phone and change their password (current password required). |

### Login

Open the app and you'll be sent to **`login.php`**. A default administrator is seeded
on first run:

> **Username:** `admin`  **Password:** `admin123` — change it from **My Profile** after signing in.

Roles: **admin** = everything incl. user management · **manager** = dashboards,
reports + **email reports** · **viewer** = dashboards & reports only. Guards live in `lib/Auth.php`.

---

## Email Reports (ported from Report Management)

The **Email Reports** page (admin/manager) generates a ranked **Driver Violations**
workbook from the `events` table and emails it to your recipient list — the same
output as the original Report Management project, adapted to FleetIQ's data model.

Built with the bundled **PHPMailer** (SMTP) and **PhpSpreadsheet** (`vendor/`).

| Capability | Notes |
|---|---|
| **Send report** | Pick a date range → builds a *Summary* sheet (drivers ranked by total violations: speeding + restricted + no-parking + overstay + idling) plus per-driver detail sheets, and emails it with a top-10 HTML table. |
| **Download only** | Generate and download the workbook without sending an email. |
| **SMTP settings** | Host / port / security / credentials / from / subject — stored in the `settings` table (no hardcoding). Password is write-only (never returned to the browser). |
| **Recipients** | Manage **To / CC / BCC** recipients, enable/disable each. Stored in `report_recipients`. |
| **Test email** | Send a one-off test to verify SMTP before a real run. |
| **Email log** | Every send (success/failure) is recorded in `email_log`. |
| **Scheduled send** | `cron/send_report.php` for Windows Task Scheduler (daily auto-send). |

### ⚠️ Real-email warning
Default SMTP credentials and the anflocor.com recipient list are **seeded from your
Report Management project and are ACTIVE**, so pressing **Send** delivers real mail
immediately. Before using in production: review the recipients, and consider
disabling the ones you don't want, or rotating the Gmail app password.

Files: `lib/Mailer.php`, `lib/ReportExcel.php`, `lib/Settings.php`,
`api/email.php`, `api/download_report.php`, `email.php`, `cron/send_report.php`.

---

## Truck Movement, Incidents & Report Builder

Three features added on top of the violations core, using the bundled **Trip report**
import and the **Google Maps** API.

### Truck Movement (`movement.php`)
Pick a driver + day → the day's **trip legs** are plotted on Google Maps with numbered
markers (green = first, red = last) and a road-snapped **Directions route** between
stops; a side panel lists each trip (time, distance, max speed). Coordinates come from
`(Lat,Lng)` in the data when present, otherwise place names are geocoded. *Movement is
trip-level (start→end places), not a raw GPS breadcrumb.*

### Incident Reports (`incidents.php`, `incident_print.php`)
Log incidents (date, truck/driver, type, severity, status, description, optional photo)
with the **location dropped as a pin on a Google Map** (lat/lng captured, address
reverse-geocoded). View any incident and **print a formatted report** with a static map.
Stored in the `incidents` table; photos in `uploads/`.

### Violations Map (`map.php`, `api/map_points.php`)
Plots every event that has stored GPS coordinates — **no geocoding needed** (instant,
free). Coordinates come from the Advanced Exceptions lat/lng and from `(Lat,Lng)` blobs
in zone/POI place fields. Filter by type/date; toggle **Pins** (colored by type, click for
details) or a **density/heatmap** view (overlapping translucent circles — Google's
HeatmapLayer was removed in Maps API v3.65, so this is the supported equivalent).

### Report Builder (`reports.php`)
On-screen builder: choose report type + driver + date range → live server-side preview,
then **Export to Excel** (`api/export_events.php`) or **Print**.

### Violation Notices & Counseling (`notices.php`, `notice_print.php`)
Generate **GPS Monitoring Violation Notices** (matching the office Word template) from the
drivers who have violations in a period — one notice per driver with a unique **Control No.**
(`YYYY` + sequence), checkbox violation counts (Overspeeding / Restricted / No-Parking /
Overstay / Excessive Idle), TOTAL, an auto-derived **Location**, and a counseling schedule.
A **monitoring page** (KPI cards + table) tracks each notice's status — **Pending → Scheduled
→ Completed / No-Show** — so admins can mark when a driver has attended counseling (records
who/when + remarks). **Print** a single notice or batch-print all of a status (e.g. *Print All
Pending*), one per page. Re-running for the same period skips drivers already issued a notice.
Files: `lib/Notices.php`, `api/notices.php`.

### Google Maps key
Stored in `settings.google_maps_key` (seeded from the key you provided). **Restrict the
key** to your site's HTTP referrer in Google Cloud Console — an unrestricted key in
client-side JS can be abused and billed to you.

Files: `lib/ReportParser.php` (trip parsing), `api/trips.php`, `movement.php`,
`api/incidents.php`, `incidents.php`, `incident_print.php`, `reports.php`,
`api/export_events.php`.

### Possible next step — auto-import from email
The source project also had `import_from_email.php` (IMAP). FleetIQ's importer
already parses every Geotab layout, so a small IMAP poller that feeds new
attachments into `lib/Importer.php` would fully close the loop. Say the word and
I'll add it.

## Scoring model (`lib/Scoring.php`)

| Event | Severity | Demerit points |
|---|---|---|
| Speeding ≥ 90 km/h | High | 10 |
| Speeding ≥ 80 km/h | Med | 6 |
| Speeding < 80 km/h | Low | 3 |
| Restricted-zone visit | High | 10 |
| No-Parking-zone visit | High | 10 |
| Overstaying | Med | 8 |
| Idling ≥ 50% | High | 8 |
| Idling 30–50% / 15–30% | Med/Low | 5 / 2 |
| Geofence visit | Low | 2 |
| POI visit / Stop | Info | 0 |

`Behavior Score = 100 − min(100, total demerit points)`
Grades: **A** ≥90 · **B** ≥80 · **C** ≥70 · **D** ≥60 · **F** <60.
Tune any of these values in `lib/Scoring.php`.

---

## Setup (XAMPP)

1. Place this folder at `C:\xampp\htdocs\GPS Report System` (already done).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open **http://localhost/GPS%20Report%20System/**
   The database `gps_report_system` and all tables are **created automatically** on first load.
4. Go to **Import Reports** and drop your Geotab `.xlsx` files.

> **Port 80 note:** On this machine port 80 is currently held by Windows IIS, so XAMPP
> Apache may fail to start on 80. Either stop IIS (`net stop W3SVC` as admin) or set
> Apache to another port (e.g. 8080) via XAMPP → Apache → Config → `httpd.conf`
> (`Listen 8080`), then browse to `http://localhost:8080/GPS%20Report%20System/`.

DB credentials live in `config/db.php` (default XAMPP: user `root`, no password).

---

## Project structure

```
config/   db.php (PDO + auto-bootstrap), schema.sql
lib/      XlsxReader.php, ReportParser.php, Importer.php, Scoring.php
api/      upload.php, dashboard.php, events.php, drivers.php, imports.php
includes/ header.php, footer.php, driver_modal.php
assets/   css/app.css, js/app.js
index.php · drivers.php · events.php · import.php
```

## Recommendations / next steps
- **Map coordinates** — many addresses carry `(Lat:…, Lng:…)`; a Leaflet heat-map of
  restricted-zone breaches would be a strong add.
- **Per-period scoring** — normalize points by km driven or active days for fairer ranking.
- **Scheduled auto-import** — point a watcher at the Geotab email/export folder.
- **Auth** — add a simple login before deploying beyond the office LAN.
