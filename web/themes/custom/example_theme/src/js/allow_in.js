(function ($, Drupal, drupalSettings, cookies, once) {
  Drupal.behaviors.ExampleThemeBehavior = {
    attach: function (context, settings) {

      // Source: iife script from https://github.com/omrilotan/isbot.
      var __defProp = Object.defineProperty;
      var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
      var __getOwnPropNames = Object.getOwnPropertyNames;
      var __hasOwnProp = Object.prototype.hasOwnProperty;
      var __export = (target, all) => {
        for (var name in all)
          __defProp(target, name, { get: all[name], enumerable: true });
      };
      var __copyProps = (to, from, except, desc) => {
        if (from && typeof from === "object" || typeof from === "function") {
          for (let key of __getOwnPropNames(from))
            if (!__hasOwnProp.call(to, key) && key !== except)
              __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
        }
        return to;
      };
      var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

      // src/browser.ts
      var browser_exports = {};
      __export(browser_exports, {
        default: () => browser_default
      });

      // src/pattern.ts
      var fullPattern = " daum[ /]| deusu/| yadirectfetcher|(?:^|[^g])news|(?<! (?:channel/|google/))google(?!(app|/google| pixel))|(?<! cu)bot(?:[^\\w]|_|$)|(?<!(?: ya| yandex|^job|inapp;) ?)search|(?<!(?:lib))http|(?<![hg]m)score|(?<!android|ios)@|\\(\\)|\\.com|^12345|^<|^[\\w \\.\\-\\(?:\\):]+(?:/v?\\d+(?:\\.\\d+)?(?:\\.\\d{1,10})*?)?(?:,|$)|^[^ ]{50,}$|^\\w+/[\\w\\(\\)]*$|^active|^ad muncher|^amaya|^avsdevicesdk/|^biglotron|^bot|^bw/|^clamav[ /]|^client/|^cobweb/|^custom|^ddg[_-]android|^discourse|^dispatch/\\d|^downcast/|^duckduckgo|^facebook|^getright/|^gozilla/|^hobbit|^hotzonu|^hwcdn/|^jeode/|^jetty/|^jigsaw|^microsoft bits|^movabletype|^mozilla/\\d\\.\\d \\(compatible;?\\)$|^mozilla/\\d\\.\\d \\w*$|^navermailapp|^netsurf|^offline explorer|^postman|^python|^rank|^read|^reed|^rest|^serf|^snapchat|^space bison|^svn|^swcd |^taringa|^thumbor/|^track|^valid|^w3c|^webbandit/|^webcopier|^wget|^whatsapp|^wordpress|^xenu link sleuth|^yahoo|^yandex|^zdm/\\d|^zoom marketplace/|^{{.*}}$|analyzer|archive|ask jeeves/teoma|bit\\.ly/|bluecoat drtr|browsex|burpcollaborator|capture|catch|check|chrome-lighthouse|chromeframe|classifier|cloud|crawl|cypress/|dareboost|datanyze|dejaclick|detect|dmbrowser|download|evc-batch/|feed|firephp|gomezagent|headless|httrack|hubspot marketing grader|hydra|ibisbrowser|images|insight|inspect|iplabel|ips-agent|java(?!;)|library|mail\\.ru/|manager|measure|neustar wpm|node|nutch|offbyone|optimize|pageburst|parser|perl|phantomjs|pingdom|powermarks|preview|proxy|ptst[ /]\\d|reputation|resolver|retriever|rexx;|rigor|robot|rss|scan|scrape|server|sogou|sparkler/|speedcurve|spider|splash|statuscake|supercleaner|synapse|synthetic|tools|torrent|trace|transcoder|url|virtuoso|wappalyzer|webglance|webkit2png|whatcms/|zgrab";

      // src/index.ts
      var naivePattern = /bot|spider|crawl|http|lighthouse/i;
      var pattern;
      function getPattern() {
        if (pattern instanceof RegExp) {
          return pattern;
        }
        try {
          pattern = new RegExp(fullPattern, "i");
        } catch (error) {
          pattern = naivePattern;
        }
        return pattern;
      }
      function isbot(userAgent) {
        return Boolean(userAgent) && getPattern().test(userAgent);
      }

      let browser_default = isbot;
      // end: iife script from https://github.com/omrilotan/isbot.


      const UA = navigator.userAgent;
      // Set allowin to false to start with.
      let allowin = false;

      if (isbot(UA) == false) {

        // Check for user logged in or other
        // bot exceptions and allow in.
        if ($('body').hasClass('user-logged-in')) {
          allowin = true;
        }

        // If user matches one of the following , let through.
        let bots = [/aolbuild/, /Pantheon-Loadtest-1.0/, /AhrefsSiteAudit/, /Googlebot smartphone/, /Google/, /Googlebot/, /Google Inspection Tool smartphone/];
        let isGoldenBot = function(agent) {
          return bots.some(function(bot) {
            return bot.test(agent);
          });
        };
        let isGolden = isGoldenBot(UA);
        if (isGolden) {
          allowin = true;
          return;
        }
      } else {
        // Definitely a bot, allow it in without TOU.
        allowin = true;
        return;
      } // end of is bot.

      // Allow in should now be set, handle all else.
      let tou_updated = drupalSettings.example_theme.tou_updated;
      let redirect_to = Cookies.get('STYXKEY_DisclaimerRedirect');
      let tou_cookie = Cookies.get('STYXKEY_Disclaimer');
      let tou_cookie_date = Cookies.get('STYXKEY_DisclaimerDate');

      // Convert 13 digit ts (with ms) to 10 digits to compare to tou_updated.
      if (typeof tou_cookie_date != 'undefined') {
        tou_cookie_date = Math.floor(tou_cookie_date / 1000);
      }

      let already_redirected = Cookies.get('STYXKEY_DisclaimerRedirected');

      // Get ts for 1 year ago.
      let now = new Date();
      let oneYearAgo = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate());
      let oneYearAgoTS = Math.floor(oneYearAgo.getTime() / 1000);

      // Set accepted cookie to old date if TOU last updated recently.
      if (typeof tou_cookie_date == 'undefined' || (tou_updated > tou_cookie_date) || (tou_cookie_date < oneYearAgoTS)) {
        let expiresDate = new Date();
        expiresDate.setFullYear(expiresDate.getFullYear() - 2);
        Cookies.set('STYXKEY_Disclaimer', 'Accepted', { sameSite: 'Lax', expires: expiresDate });
        let timestamp = new Date().getTime();
        Cookies.set('STYXKEY_DisclaimerDate', timestamp, { sameSite: 'Lax', expires: expiresDate });
      }

      // Accepted tou but haven't shown modal/overlay yet.
      if (tou_cookie && tou_cookie == 'Accepted' && redirect_to && typeof already_redirected == 'undefined') {
        allowin = true;
        return;

      // Accepted tou or is a bot.
      } else if (allowin || (tou_cookie && tou_cookie == 'Accepted' && (already_redirected || (typeof already_redirected == 'undefined' && typeof redirect_to == 'undefined')))) {
        // Do nothing, just let user in.
      } else {
        // Not accepted tou.
        // Do not require tou for specific pages.
        let allowed_paths = [
          '/webform/javascript/disclaimer', '/webform/css/disclaimer',
          '/sitewide_alert/load', '/quickedit/attachments', '/batch',
          '/node/13516', '/Privacy-Policy', '/'
        ];

        if ($.inArray(window.location.pathname, allowed_paths) > -1) {
          return;

        } else {
          // Set full path to redirect to, including query.
          let path = window.location.pathname + window.location.search;
          if (typeof Cookies.get('STYXKEY_DisclaimerRedirect') == 'undefined' || path != '/') {
            Cookies.set('STYXKEY_DisclaimerRedirect', path);
          }
          $("#block-tou .modal").modal("show");
          $("#block-tou .modal input#link").val(redirect_to);
        }

        $("a").click(function (e) {
          let tou_cookie = Cookies.get('STYXKEY_Disclaimer');
          let link = $(this).attr('href');

          // Allow user to view certain pages.
          if (link == '/Privacy-Policy') {
            return;
          }

          // User clicks link but hasn't acceepted TOU,
          // reset redirect to link and show modal.
          if (!(tou_cookie && tou_cookie == 'Accepted')) {
            e.preventDefault();
            Cookies.set('STYXKEY_DisclaimerRedirect', link)
            if ($("#block-tou .modal").length) {
              $("#block-tou .modal").modal("show");
              $("#block-tou .modal #link").val(link);
            }
          }
        });
      } // End if false.

      // Handle accepting TOU in modal.
      $("button.modal-close").click((e) => {
        e.preventDefault();
        let link = $('#link').val();
        if ($('#tou').is(':checked')) {
          Cookies.set('STYXKEY_Disclaimer', 'Accepted', { sameSite: 'Lax', expires: 365 });
          let timestamp = new Date().getTime();
          Cookies.set('STYXKEY_DisclaimerDate', timestamp, { sameSite: 'Lax', expires: 365 });
          let redirect_to = cookies.get('STYXKEY_DisclaimerRedirect');
          if ((redirect_to == link) || (redirect_to == '/' && link != '/')) {
            Cookies.set('STYXKEY_DisclaimerRedirected', true);
            let current_page = window.location.pathname + window.location.search;
            if (current_page == '/') {
              window.location.href = link;
            }
            $("#block-tou .modal").modal("hide");
            return;
          }
          if (redirect_to) {
            Cookies.set('STYXKEY_DisclaimerRedirected', true);
            //window.location.href = redirect_to;
          } else {
            // Shouldn't get here, but just in case.
            Cookies.set('STYXKEY_DisclaimerRedirected', true);
            //window.location.href = link;
          }
          $("#block-tou .modal").modal("hide");
        }
      });

      // Handle closing modal.
      $("#block-tou button.close").click((e) => {
        window.location.href = '/';
      });

      // Handle user clicking out of modal.
      let outsideModal = document.getElementById('page');
      if ($('.modal.show').length) {
      outsideModal.addEventListener("hide.bs.modal", function(e) {
        let check_cookie = Cookies.get('STYXKEY_Disclaimer');
        // If user hasn't accepted tou, redirect to home.
        if ((allowin == false && (!tou_cookie || tou_cookie != 'Accepted')) && (check_cookie != 'Accepted')) {
          window.location.href = '/';
        }
      });
      }

      // Search modal shouldn't work if TOU not accepted.
      let searchCollapsible = document.getElementById('collapseSearch');
      if (searchCollapsible) {
        searchCollapsible.addEventListener("show.bs.collapse", function (e) {
          let tou_cookie = Cookies.get('STYXKEY_Disclaimer');
          if ((typeof tou_cookie == 'undefined' || tou_cookie == false || (tou_cookie && tou_cookie != 'Accepted'))) {
            // Load terms modal instead of search modal.
            e.preventDefault();
            $("#block-tou .modal").modal("show");
            $("#main-wrapper").css("filter", "none").css("opacity", "1").css("pointer-events", "inherit");
            $(".featured-top").css("filter", "none").css("opacity", "1").css("pointer-events", "inherit");
          }
        });
      }

      // Make sure search on 404 pages doesn't work.
      let search404 = $('.form-type-search-api-autocomplete input');
      $(search404).focus(function(){
        let tou_cookie = Cookies.get('STYXKEY_Disclaimer');
        if ((typeof tou_cookie == 'undefined' || tou_cookie == false || (tou_cookie && tou_cookie != 'Accepted'))) {
          $(this).blur();
        }
      });

    }
  }

})(jQuery, Drupal, drupalSettings, window.Cookies, once);
