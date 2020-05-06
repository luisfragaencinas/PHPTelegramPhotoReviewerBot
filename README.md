# PHPTelegramPhotoReviewerBot
PHP Telegram bot that allows users to send photos that will be stored in the server after a reviewer aproves them, also using the bot

## Summary
First create a telegram bot and note down the token

configure configuration.ini with correct values

www.acme.com/linkbot.php?password=xxxxx will link the telegram bot with photoreview.php

Open a chat with the bot and /start it, instructions should then appear

## configuration.ini
```
masterpass = password needed to invoke linkbot.php
password = password to become a reviewer in the bot
photos_route = route where the photos should be saved (folder should exist first)
reviewers_file = shared file between bot instances to store curren reviewers IDs
telegram_img = 'https://api.telegram.org/file/bot' telegram img URL
; Accept image bot
token_accept = token provided when bot is created
url_accept = public HTTPS photoreview.php bot location
```



