# exodus-fdroid

CLI tool to scan F-Droid packages with [εxodus](https://exodus-privacy.eu.org/en/).

## Install

The easiest way to install this tool is with [Composer](https://getcomposer.org/):

```bash
composer global require rudloff/exodus-fdroid
```

## Usage

You need to pass the ID of the F-Droid app you want to scan:

```bash
exodus-fdroid org.wikipedia
```

It will then list the trackers the latest release of this app contains.
