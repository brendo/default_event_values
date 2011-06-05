# Default Event Values

* Version: 0.2
* Author: Brendan Abbott
* Build Date: 5rd June 2011
* Requirements: Symphony 2.2

Adds the ability to default values for your Events. This allows you to set a field's value while omitting the hidden field from your form markup, which is useful to boost security. Values can be page parameters, excluding datasources though (as Events run before Datasources).

You currently cannot fix the ID of an Event due to a Symphony limitation.

Values can be added as defaults, which can be overridden by users, or can be made to override any value that is posted via the Frontend. Please note that other extensions that use the EventPreSaveFilter may change the values after this extension has done it's business.

## How do I use it?

1. Extract and install this extension to your `/extensions` folder

2. While creating your events in the Event Editor, a new duplicator will appear after the Filter Options settings.

3. Save your event as normal and take a tequila shot, because tonight's going to be a good night.