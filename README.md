# Filterable Events Plugin
This is a simple, lightweight events plugin for use in Wordpress via a shortcode (tbc). It creates a new CPT called events, and allows uses to register interest on events based on a number of attendees set at Events Level.

Number of users when set at event level are displayed on the front end, as well as the amount of spaces left per event. User data is collected at event level and displayed on the actual post. 

Points to note:

- Users must be a registered user on the Wordpress site to be able to register interest in the event. If not, the event states users must be logged in to register interest.
- Once user has already expressed interest, the user is shown a message on the event stating they have already expressed interest in the event.
- User email at username level is stored in a separate table in WP Database
- Simple Select box change based on event type


## Interations to come
- Taking into account time zones of different countries
- Discuss storing users in a CPT rather than a Database table
- Destroy DB table on uninstallation of plugin (to  do)
- Sort by date
- CSV download button at Event level to download the people that have shown interest

## Wordpress Details

This has been tested with the latest version of Wordpress 6.8.1
