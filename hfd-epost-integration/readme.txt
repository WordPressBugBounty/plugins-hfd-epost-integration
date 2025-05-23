=== HFD ePost Integration ===
Contributors: oninitay, hfdepost
Tags: woocommerce, sync, hfd, epost, shipping
Donate link: 
Requires at least: 4.0
Tested up to: 6.8
Stable tag: 2.18
Requires PHP: 5.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

התוסף מאפשר סנכרון בין אתר וורדפרס למערכת המשלוחים HFD.
התממשקות חד צדדית עם HFD הכוללת שליחת הזמנות, ביטול הזמנות ומעקב אחרי ההזמנות בווקומרס.

== Description ==
התוסף מאפשר סנכרון בין אתר וורדפרס למערכת המשלוחים HFD.
התממשקות חד צדדית עם HFD הכוללת שליחת (סנכרון) הזמנות, ביטול הזמנות ומעקב אחרי ההזמנות בווקומרס.
בלחיצת כפתור תוכל לשדר את ההזמנות שלך ישירות ל-HFD ללא צורך בתיאום עם חברת  המשלוחים.
להסבר מפורט על הגדרת התוסף לחץ כאן:
https://www.hfd.co.il/%d7%a4%d7%aa%d7%a8%d7%95%d7%a0%d7%95%d7%aa-%d7%98%d7%9b%d7%a0%d7%95%d7%9c%d7%95%d7%92%d7%99%d7%99%d7%9d/wordpress-plugin/


== Installation ==
How to install the plugin and get it working.

תיאור המודול
המודול מוסיף אפשרות משלוח EPOST לצ'קאאוט בו הגולש יכול לבחור את נקודת האיסוף של EPOST ממנה הוא רוצה לאסוף את ההזמנה, בנוסף המודול מאפשר שליחה של פרטי ההזמנה ישירות ל-HFD WEBSERVICE בלחיצת כפתור.

התקנה וקונפיגורציה של המודול בWooCommerce
יש להתקין את המודול תחילה בסביבת הפיתוח ולבצע בדיקות לוודא שהכל עובד תקין
לצורך בדיקות ניתן להשתמש בחשבון טסטים
בקונפיגורציה של הפלאגין יש למלא את השדות הבאים:
Woocommerce->Betanet epost settings
יש למלא את השדות הבאים:
Betanet Epost Setting

Service URL - Google map API key -
Google map API key: יש להכניס קוד API
יצירה של KEY עבור GOOGLE MAPS - יש לפעול לפי ההנחיות כאן:

HFD Configuration
Enable HFD Integration -
לבחור YES
Service URL -
Allow shipping method - יש לסמן את סוגי המשלוח שרוצים לסנכרן ל-HFD
Sender name - יש למלא את השם של החברה - כך זה יופיע במסוף של HFD

Customer number
מספר לקוח ב-HFD - יש לבקש זאת מ-HFD

Order Prefix
 - אופציונלי, קידומת שתופיע לפני מספר ההזמנה - יופיע בשדה אסמכתא ב-HFD, במידה ומשאירים ריק ישלח רק את המספר הזמנה כאסמכתא
לאחר התקנת הפלאגין יש לשייך את סוג המשלוח EPOST ל-WOOCOMERCE
כך שיופיע בצ'קאאוט

use custom jquery ui -
בדיפולט זה NO
אם בוחרים NO זה ישתמש ב-JQUERY UI דיפולט של וורדפרס
אם בוחרים YES- זה ישתמש ב- JQUERY U של הפלאגין

לבחור NO.

הפעלת הסנכרון בין WooCommerce ומערכת HFD

כדי לשלוח הזמנות למערכת HFD, ב- WooCommerce יש לעבור לרשימת ההזמנות,
בעמודה HFD Actions יש ללחוץ על הלחצן Sync to HFD בשורה של ההזמנה שנדרש לסנכרן אותה עם מערכת HFD

לאחר סנכרון מוצלח הלחצן Sync to HFD יהפוך ל- Print Label שמאפשר להדפיס מדבקה

Plugin uses third party Google Maps API to add pickup branch in google map

== Privacy Policy ==
HFD - https://www.hfd.co.il/privacy-policy/

== Third Party API ==
Google Maps - maps.googleapis.com
HFD - ws.hfd.co.il

== Changelog ==
= 1.0 =
* Initial release

= 2.0 =
* Performance Improvement
* Integrated Latest google maps api

= 2.2 =
* Fixed autocomplete place in google map

= 2.3 =
* Converted API to JSON
* Added option to send order items

= 2.5 =
* Improved autocomplete in google map

= 2.7 =
* Compatible with gutenberg

= 2.8 =
* Performance Improvement

= 2.9 =
* Performance Improvement

= 2.10 =
* Performance Improvement

= 2.11 =
* Option added to take variation sku if exist

= 2.12 =
* Changed API url and Improved order auto sync code

= 2.13 =
* Performance Improvement

= 2.14 =
* Performance Improvement

= 2.15 =
* Performance Improvement

= 2.16 =
* Performance Improvement

= 2.17 =
* Performance Improvement
* select box design issue fixed
* added wp_date function instead of date

= 2.18 =
* Performance Improvement

== Frequently Asked Questions ==
= Why Google Maps isnt loading =
Need to add Google map API key
Need to create Google Maps Key following the orders 

= Where I can find my Customer number = 
Ask HFD support for your customer number

= Which Shipping Methods are available = 
Need to choose the Shipping Methods you want in order to synchronize with HFD

= How many HFD pickup branch layout exist in plugin = 
There are two layout exists
1. Map( Google Map )
2. List

== Screenshots ==
1. HFD Settings
2. Checkout shipping area
3. Checkout map area for local pickup