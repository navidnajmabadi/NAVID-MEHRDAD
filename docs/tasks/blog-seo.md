# Chat: blog, sitemap, robots, llms

Branch: `cursor/claude-blog-seo`

| # | Task | Status | Notes |
|---|------|--------|-------|
| 51 | Admin panel: a part to upload blog posts | Done | Admin → Blog (new "Content" group in the menu). Write a post, or upload a .md / .txt / .html file that fills the editor; cover picture; draft or publish; schedule; hide; delete; preview a draft first. Tested locally: create, edit, replace cover, hide, schedule, delete, wrong CSRF, bad cover file, hostile HTML in every field (always shown as text) |
| 52 | Main SIPAW site: published posts appear on the blog | Done | `/blog` and `/blog/<address>` read the database (the three starter posts were moved in by migration 030 and can be edited or hidden). The landing page has a "From the blog" block (latest 3). Pretty addresses work with the existing `.htaccess` rules |
| 53 | robots.txt so systems can read the site | Done | Real rules with the sitemap link (the old file had an empty Sitemap line). Search engines and AI assistants may read public pages; admin, API, signed-in screens and pet pages are blocked for everyone. Checked with a crawler parser: 12 bots x 26 paths, 0 mismatches. Also `noindex` on all app screens |
| 54 | llms.txt (and llms-full.txt) | Done | `/llms.txt`: plain description of SIPAW, pages and the blog list; `/llms-full.txt`: plus the full text of published posts. Public information only |
| 55 | sitemap.xml | Done | Built live from the published posts |
| 56 | Be found by Google and Bing | Pending | Needs you: add the site in Google Search Console and Bing Webmaster Tools and submit `https://sipaw.nakilab.com/sitemap.xml` (ownership check is yours) |
| 57 | Write real blog posts | Pending | Needs you: the three starter posts are placeholders from earlier. Use "SI" or "Super Intelligence", not "AI" (the editor warns) |
| 58 | Small fixes found on the way | Done | Blog list and posts had no side padding on phones (text touched the screen edge). My docs said the lost-pet radius is 25 km; the code default is 20 km (`lost_radius_km`), now corrected |
