# code_samples
A few Drupal module &amp; theme code samples

## Sample Modules
### center_closures_import
Custom CSV importer for center closure data to be imported via cron daily.

### example_chat
Rocket.Chat integration that ties creation/deletion of channels to conferences (a content type).


## Sample JS in the theme
### allow_in.js
Requires users to have accepted terms of use before accessing most pages on the site but allows white listed bots (like Googlebot and other necessary bots) to access pages.

### contact-switcher.js
Provides a simple selector where the user chooses what type of contact form they need and then redirects the user to that webform.

### currency_mask.js
Adds an inputmask to ensure that only correctly formatted currency numbers are allowed in an amount field.

### pagination-scroll-top.js
Ensures that the user is scrolled back to the top of content after ajax calls on a Solr based view.

### tabledrag.js
Extends core's Tabledrag functionality

### validate_keyword_search.js
Validation of at least 2 letters in keyword field for search before submitting.


## Sample package.json & gulpfile
There is a sample package.json file for required Node.js packages and a sample gulpfile to build CSS and JS for production.


## Pre requisites

| Tool     | Version    |
| -------- | ---------- |
| Node     |  17.6.0 +  |
| NPM      |  8.5.1 +   |
| Yarn     |  1.22.17 + |
| Gulp     |  4.0.0 +   |
| Gulp CLI |  2.3.0 +   |


## Start Gulp task manager

```bash
gulp
```
