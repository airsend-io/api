This folder holds a list of accepted emojis.

This list id formed by a list of ranges (UTF-8 code ranges), and it's extracted from this file:

https://www.unicode.org/Public/UCD/latest/ucd/emoji/emoji-data.txt

The list is a json file: `emoji_ranges.json`.

Basically we recognize as an emoji, any char that unicode considers an emoji.

To update this list (grabbing a fresh list from unicode.org), we can run the `update_emoji_ranges.php` script.