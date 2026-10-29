# SaaS installer and manager

This folder is the brief for the next piece of work. It is not the self-hosted installer. Do not put that work in `src/Platform/SetupScreen.php`.

The first task is a design proposal. Do not write the installer, the manager, or a schema until that proposal is the agreed shape.

## What is already built

SaaS mode is on when `INLAY_STORES` and `INLAY_OPERATOR_HOST` are set, in the environment or in `config/platform.json` beside the code. Environment values win. Leave both empty and the server is one self-hosted site.

The operator host is a separate login in `platform.sqlite`, beside the stores folder. It is not a user inside a customer site. The screen is `Adlexone\Platform\OperatorScreen`. The tables are `operators` and `stores` in `Adlexone\Platform\PlatformDb`.

Each customer is one folder, `{stores}/{slug}/`, with `adlexone.sqlite`, `config/`, and `attachments/`. One request opens one SQLite file. `Adlexone\Platform\Provisioner` creates and removes that folder. `Provisioner::folderBytes()` is the only usage figure on the operator screen. It is the size of the folder.

The operator can create a site, suspend it, resume it, download a backup, and delete the folder after the slug is typed. There is no screen to change the label, hostname, or contact email after creation. There is no installer that turns a new server into SaaS mode. Those two values are edited by hand.

A new customer with no administrator still finishes on their own hostname. That page is theirs. It is not this manager.

The self-hosted installer is the named flow in `SetupScreen`: introduction, document root, storage directory, account. Leave it alone unless a sentence in it would become false.

## What this work is

Two tools, for the person who runs the server:

1. **SaaS installer.** The first visit that turns this copy of Adlexone into a host of customer sites. It chooses the stores directory and the operator hostname, checks that PHP can create, read, update, and delete files there, checks that a browser cannot read that directory, and creates the operator account.

2. **Site manager.** Create, read, update, and delete hosted sites. Show end-to-end usage, and the cost of that usage, for one site and for the whole host.

## What the design must decide

Write the proposal in this folder. Answer these in the proposal. Do not leave them as an open list.

- Where the installer sits relative to the self-hosted installer, and how a server that is already in SaaS mode never shows it again.
- What Update changes on a site (label, hostname, contact), and what it must not change (the slug, the folder, another site's files).
- What "end to end usage" is made of. Today the only measure is folder size. A useful total splits the database, the SQLite `-wal` and `-shm` files, settings, and uploads, then adds counts that describe the site: people, applications, records. Say which of those are read from the customer SQLite file and which are read from disk.
- What "cost" is. There is no price list and no invoice. The proposal says whether the operator types a rate (for example a monthly amount, plus an amount per person, plus an amount per gigabyte) and the manager multiplies, or whether the first version shows usage only and cost comes later. If there is a rate, say where it is stored. It does not belong in a customer database.
- How a total across sites is produced without opening every customer database on every page view.
- How delete, suspend, and backup stay as they are: suspend keeps the files and stops sign-in, backup checkpoints SQLite then downloads the folder, delete removes the folder only after the slug is typed.

## Shape of the proposal

One document, `design/saas-installer/proposal.md`. Plain sentences. Name the screens, the files, and the figures. No slogans.

The self-hosted installer is the reference for tone and for the checks: document root, a directory PHP can use, and a folder a browser cannot read. The SaaS installer is a different page. The manager is a different page from both.
