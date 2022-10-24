# Newsletter

Newsletter is a Iris library for generating forms. Useless if you don't use Iris CMS.

## Installation
1. Run command:
```bash
composer require "themindoffice/newsletter" @dev
```
*(Stable version coming soon)*


2. Copy 'vendor/themindoffice/newsletter/src/' to 'modules/Addons'
3. Go to your-local-domain.test/newsletter/install
> This function add the needed tables and components.
4. Create 'detail_iris_nieuwsbrieven.php' in 'resource/templates'

## Usage

1. Add this in your .env file with the right credentials. Make sure the domain is whitelisted at Mandrill.
```
APP_DOMAIN=https://example.com

MAIL_FROM_ADDRESS=example@mail.com
MAIL_FROM_NAME="Example"
```

2. Set the cronjob for sending.