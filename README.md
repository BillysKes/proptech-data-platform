# PropTech Data Platform Showcase

> **Disclaimer:** This repository contains isolated and sanitized core logic from a live production real estate platform. Proprietary business rules, authentication keys, database credentials, and full application structures have been scrubbed to comply with strict confidentiality and security standards.

## 1. Project Overview
This repository demonstrates the backend architecture, data engineering, and spatial algorithms used to deploy a highly scalable real estate platform. Serving as the sole platform engineer, the core challenge was bridging a legacy SQL-based CRM with a modern web frontend while completely eliminating manual data entry, optimizing storage, reducing latency, and building a dynamic geospatial search engine where some of its search filters are hidden or shown depending on the characteristics of the user's selected options.

## 2. Directory Navigator
The core data engineering logic is concentrated in the following files:
* `bridges_update.php`: Contains the automated ETL batch processing, bidirectional API syncing, and ghost-listing cleanup logic.
* `includes/rest-api-sync.php`: Houses the custom REST API endpoints, the Point-in-Polygon geospatial algorithm, and the AWS media virtualization hooks.
* `includes/search-overrides.php`: Demonstrates backend search optimization via complex SQL meta-query interception (`pre_get_posts`).
* `realhomes-functions.php`: Showcases the data modeling and translation logic used to structure raw CRM data into the platform's schema.

## 3. Core Architecture & Isolated Modules

### A. Automated ETL Pipeline & Bidirectional Sync (`bridges_update.php`)
To ensure 100% inventory accuracy without manual intervention, an automated ETL pipeline was engineered using scheduled PHP cronjobs and a custom REST API. 
* **Batch Processing:** Data payloads from the external CRM are chunked into optimized batches (e.g., 5 listings per request) to prevent server timeout errors during heavy data migrations.
* **Bidirectional State Acknowledgments:** Once a batch is successfully ingested and mapped to the platform's database, the script fires an acknowledgment webhook (`action=ack`) back to the legacy CRM to update the synchronization state of each listing.
* **Automated Ghost Cleanup:** The pipeline continually cross-references active platform IDs with live CRM IDs, autonomously detecting and purging "ghost" listings (sold or removed properties) to maintain strict database integrity.

### B. Geospatial Search & Point-in-Polygon Algorithm (`includes/rest-api-sync.php`)
Traditional real estate searches rely on human agents manually tagging properties to specific municipalities. This creates data quality issues if tags are forgotten or misspelled.
* **Mathematical Geofencing:** Implemented a custom Ray-Casting (Point-in-Polygon) algorithm from scratch (`rh_point_in_polygon`). 
* **Dynamic Location Assignment:** The algorithm extracts the raw latitude and longitude coordinates of incoming CRM properties and mathematically calculates if the pin falls inside predefined geographic boundary polygons (`rh_run_background_geofencing`). 
* **Impact:** This significantly enhanced search retrieval by surfacing nearby listings based on actual geographic boundaries, entirely overriding the reliance on static, error-prone manual location assignments.

### C. Cloud Media Virtualization (`includes/rest-api-sync.php`)
Real estate platforms are exceptionally heavy on image hosting, which rapidly inflates local server storage costs and degrades load performance. 
* **Database Query Interception:** Native CMS media queries (e.g., `wp_get_attachment_url`, `image_downsize`) were intercepted and overridden.
* **Ghost Attachments:** Instead of downloading thousands of high-resolution property photos to the local server, the database was engineered to store "ghost" attachment IDs containing external AWS S3 URLs which is also configured with Cloudfront. 
* **Dynamic WebP CDN Proxy:** The intercepted URLs are routed through an external image proxy (`wsrv.nl`) that resizes images on-the-fly to create thumbnail versions and converts them to WebP formats before delivering them via a global CDN.
* **Impact:** Completely eliminated local file storage for property media, reducing the server footprint to near-zero while drastically improving page load latency.

### D. Backend Search Optimization (`includes/search-overrides.php`)
* **Meta-Query Restructuring:** Overhauled the core search engine by intercepting and restructuring complex backend SQL queries (`pre_get_posts`), enforcing strict multi-variable dependent logic for features like dynamic price-per-square-meter calculations and specific facility requirements.

## 4. Technology Stack
* **Languages:** PHP 8+, SQL, JavaScript, HTML5/CSS3.
* **Data Engineering:** REST API Architecture, JSON Batch Processing, ETL Pipelines.
* **Algorithms:** Geospatial Mathematics (Ray-Casting / Point-in-Polygon).
* **Cloud & Edge:** AWS S3, Cloudflare (DNS/SSL), WebP Edge Rendering.
