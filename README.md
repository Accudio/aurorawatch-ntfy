# AuroraWatch UK ntfy notification bridge

An unofficial and simple way of getting [AuroraWatch UK](https://aurorawatch.lancs.ac.uk) notifications on devices via ntfy.

Uses the [AuroraWatch UK API](https://aurorawatch.lancs.ac.uk/api-info/) to check aurora status and [ntfy](https://ntfy.sh) for delivering notifications.

## Usage

First, install the ntfy app on your chosen device:

- [Google Play Store (Android)](https://play.google.com/store/apps/details?id=io.heckel.ntfy&pli=1)
- [Apple app store (iOS)](https://apps.apple.com/us/app/ntfy/id1625396347)
- [Progressive Web App (Windows, macOS, linux, other)](https://ntfy.sh/app)
- [F-Droid (Android)](https://f-droid.org/en/packages/io.heckel.ntfy/)

Then, subscribe to the [aurorawatch](https://ntfy.sh/aurorawatch) topic on the default ntfy.sh server.

## Development

The project is a simple PHP script in `cron.php`. Run it via the command line or on a cron job with `php cron.php`. It uses composer for dependencies but includes them in the repo so there should be no need for installation.

`state.json` is used to store the previous state of the script to avoid duplicate alerts and to track the cool-down of alerts and alert escalation.

Notification behaviour and content is designed to mirror the 'official' AuroraWatch UK apps as closely as possible.

## Credit

Built by Alistair Shepherd. Relies on AuroraWatch UK and ntfy projects heavily.

This project does not have an open-source license, so is not licensed for re-use. However the code is publicly accessible so feel free to use it as inspiration.