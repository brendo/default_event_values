# Default Event Values

* Version: 0.4
* Author: Brendan Abbott
* Build Date: 6th June 2011
* Requirements: Symphony 2.2

Adds the ability to default values for your Events. This allows you to set a field's value while omitting the hidden field from your form markup. Values can be page parameters, excluding Datasources though as Events run before Datasources. You currently cannot set a default value for the ID of an Event due to a Symphony limitation.

Values can be added as defaults, which can be overridden by users, or can be made to override any value that is posted via the Frontend. Please note that other extensions that use the EventPreSaveFilter may change the values after this extension has done it's business.

## How do I use it?

1. Extract and install this extension to your `/extensions` folder

2. While creating your events in the Event Editor, a new duplicator will appear after the Filter Options settings.

3. Save your event as normal and take a tequila shot, because tonight's going to be a good night.

## Things to be aware of

* Default values will not be added to the `post-values` Event XML
* This extension currently does not support setting default values for things like `send-mail[sender-email]`, but it is planned.

## What's changed?

_0.4_ (6th June 2011)

* Ability to add custom Default Values