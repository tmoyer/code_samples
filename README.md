# code_samples
A few Drupal module &amp; theme code samples

## Sample Modules
### center_closures_import
Custom CSV importer for center closure data to be imported via cron daily.

#### Problem Addressed
The client needed to have daily updates about which centers were closed and which were operational for users to easily access. This was solved by them posting a CSV file to an AWS S3 bucket daily which the site then pulled in and consumed to update each center's status.

#### Technologies Used
* AWS S3 for file storage
* PHP to retrieve and ingest data
* MySQL database to store the latest updates
* Cron to time data imports


### example_chat
Rocket.Chat integration that ties creation/deletion of channels to conferences (a content type).


## Sample JS in the theme
### allow_in.js
Requires users to have accepted terms of use before accessing most pages on the site but allows white listed bots (like Googlebot and other necessary bots) to access pages.

#### Problem Addressed
The client needed to ensure users accept their terms of use before accessing most pages on the site, but also allow non-malicious bots access to data for indexing, Google search visibility, and third-party approved bots for other uses.

#### Technologies Used
* isbot library from https://github.com/omrilotan/isbot to dentify bots, crawlers, and spiders using the user agent string.
* Javascript and jQuery


### contact-switcher.js
Provides a simple selector where the user chooses what type of contact form they need and then redirects the user to that webform.

#### Problem Addressed
Client wanted to provide different webforms depending on the user's need.

#### Technologies Used
* Javascript & jQuery


### currency_mask.js
Adds an inputmask to ensure that only correctly formatted currency numbers are allowed in an amount field.

#### Problem Addressed
Client wanted to ensure that only properly formatted currency numbers were allowed in a dollar amount input field.

#### Technologies Used
* Javascript & jQuery
* Drupal Webform module's inputmask library
* jQuery inputmask library from https://github.com/RobinHerbots/jquery.inputmask


### pagination-scroll-top.js
Ensures that the user is scrolled back to the top of content after ajax calls on a Solr based view.

#### Problem Addressed
When a user used the client site's search, then updated that search or navigated to an additional page of results, where was all handled by AJAX, the users would expect to be taken to the top of the new or updated results, not stay where they were on the page when they initiated the change.

#### Technologies Used
* Javascript & jQuery


### tabledrag.js
Extends core's Tabledrag functionality

#### Problem Addressed
When the user drags item to a table or within the table they should see a warning that they have unsaved changes. They may also want to toggle row weight visibility.

#### Technologies Used
* Javascript & jQuery


### validate_keyword_search.js
Validation of at least 2 letters in keyword field for search before submitting.

#### Problem Addressed
Client wanted to ensure that users could only submit internal site searches if there were at least 2 letters to search for and otherwise disable search.

#### Technologies Used
* Javascript & jQuery



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
