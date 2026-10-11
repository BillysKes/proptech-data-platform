# PropTech Data Platform Showcase

> **Disclaimer:** This repository contains isolated and sanitized core logic from a live production real estate platform. Proprietary business rules, authentication keys, database credentials, and full application structures have been scrubbed to comply with strict confidentiality and security standards.

## 1. Project Overview
This repository demonstrates the backend architecture, data engineering, and spatial algorithms used to deploy a highly scalable real estate platform. Serving as the sole platform engineer, the core challenge was bridging a legacy SQL-based CRM with a modern web frontend while completely eliminating manual data entry, optimizing storage, reducing latency, and building a dynamic geospatial search engine where filters intelligently adapt to user selections.

## 2. Directory Navigator
For engineering managers reviewing this codebase, the core data engineering logic is concentrated in the following sanitized files:
* `bridges_update.php`: Contains the automated ETL batch processing, bidirectional API syncing, and ghost-listing cleanup logic.
* `includes/rest-api-sync.php`: Houses the custom REST API endpoints, the Point-in-Polygon geospatial algorithm, and the AWS media virtualization hooks.
* `includes/search-overrides.php`: Demonstrates backend search optimization via complex SQL meta-query interception (`pre_get_posts`).
* `realhomes-functions.php`: Showcases the data modeling and translation logic used to structure raw CRM data into the platform's schema.
* `js/src/03-search-rules.js`: Contains the event-driven DOM manipulation for the dynamic search interface.

## 3. Core Architecture & Isolated Modules

### A. Automated ETL Pipeline & Bidirectional Sync (`bridges_update.php`)
To ensure 100% inventory accuracy without manual intervention, an automated ETL pipeline was engineered using scheduled PHP cronjobs and a custom REST API. 
* **Batch Processing:** Data payloads from the external CRM are chunked into optimized batches (e.g., 5 listings per request) to prevent server timeout errors during heavy data migrations.
* **Bidirectional State Acknowledgments:** Once a batch is successfully ingested and mapped to the platform's database, the script fires an acknowledgment webhook (`action=ack`) back to the legacy CRM to update the synchronization state of each listing.
* **Automated Ghost Cleanup:** The pipeline continually cross-references active platform IDs with live CRM IDs, autonomously detecting and purging "ghost" listings (sold or removed properties) to maintain strict database integrity.

### B. Geospatial Search & Point-in-Polygon Algorithm (`includes/rest-api-sync.php`)
Traditional real estate searches rely on human agents manually tagging properties to specific municipalities. This creates data quality issues if tags are forgotten or misspelled.
* **Mathematical Geofencing:** Implemented a custom Ray-Casting algorithm (`rh_point_in_polygon`) from scratch to evaluate coordinate data without relying on paid external mapping APIs. The mathematical model casts a horizontal ray rightward from the property's coordinate point and counts its intersections with the polygon's edges. By applying a modulus operation, an odd number of intersections mathematically confirms the property falls strictly inside the geofence.
* **Dynamic Location Assignment:** The background geofencing logic (`rh_run_background_geofencing`) is automatically triggered via the custom `/sync-locations` REST endpoint. Whenever a geographic polygon is created or modified in the external CRM, the platform intercepts the webhook, recalculates all property coordinates, and dynamically reassigns the affected inventory to the updated geographic buckets.
* **Impact:** This significantly enhanced search retrieval by surfacing nearby listings based on actual geographic boundaries, entirely overriding the reliance on static, error-prone manual location assignments.

### C. Cloud Media Virtualization (`includes/rest-api-sync.php`)
Real estate platforms are exceptionally heavy on image hosting, which rapidly inflates local server storage costs and degrades load performance. 
* **Database Query Interception:** Native CMS media queries (e.g., `wp_get_attachment_url`, `image_downsize`) were intercepted and overridden.
* **Ghost Attachments:** Instead of downloading thousands of high-resolution property photos to the local server, the database was engineered to store "ghost" attachment IDs containing external AWS S3 URLs.
* **Dynamic WebP CDN Proxy:** The intercepted URLs are routed through an external image proxy (`wsrv.nl`) that resizes images on-the-fly and converts them to next-gen WebP formats before delivering them via a global CDN.
* **Impact:** Completely eliminated local file storage for property media, reducing the server footprint to near-zero while drastically improving page load latency.

### D. Backend Search Optimization (`includes/search-overrides.php`)
* **Meta-Query Restructuring:** Overhauled the core search engine by intercepting and restructuring complex backend SQL queries (`pre_get_posts`), enforcing strict multi-variable dependent logic for features like dynamic price-per-square-meter calculations and specific facility requirements.

### E. Event-Driven Frontend Search UI (`js/src/03-search-rules.js`)
* **State-Based DOM Manipulation:** Engineered a dynamic configuration dictionary that maps specific property types (e.g., residential vs. commercial) to allowed search parameters. Event listeners monitor dropdown states and dynamically hide irrelevant filters (e.g., hiding "Garden" for office spaces) while revealing context-specific ones, drastically improving UX.

## 4. Technology Stack
* **Languages:** PHP 8+, SQL, JavaScript, HTML5/CSS3.
* **Data Engineering:** REST API Architecture, JSON Batch Processing, ETL Pipelines.
* **Algorithms:** Geospatial Mathematics (Ray-Casting / Point-in-Polygon).
* **Cloud & Edge:** AWS S3, Cloudflare (DNS/SSL), WebP Edge Rendering.
