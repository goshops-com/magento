# GoPersonal Magento Plugin

## Description

The GoPersonal Magento Plugin enables seamless communication between your Magento 2 store and the GoPersonal personalization platform. GoPersonal is a powerful solution that allows you to create personalized experiences for your customers, enhancing engagement and driving conversions.

This module acts as a bridge, facilitating data exchange and integration between your Magento store and GoPersonal's personalization services.

## Requirements

- Magento 2.3.x or higher
- PHP 7.0 or higher

## Installation

You can install this module via Composer. Follow these steps:

1. Ensure you have Composer installed and configured with your Magento 2 project.

2. Run the following command in your Magento root directory to install the latest version from the main branch:

```bash
composer require gopersonal/magento-plugin:dev-main
```

   If you prefer to install a specific version, you can specify the version number instead:

```bash
composer require gopersonal/magento-plugin:1.0.17
```

   Replace `1.0.16` with the desired version number.

3. Once the installation is complete, enable the module by running:

```bash
bin/magento module:enable Gopersonal_Magento
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento setup:upgrade
```

4. Clear the cache:

```bash
bin/magento cache:clean
```

## Upgrading

To upgrade the GoPersonal Magento Plugin to the latest version, follow these steps:

1. From your Magento root directory, run the following command:

```bash
composer require gopersonal/magento-plugin:dev-main --update-with-dependencies
```

This command will update the module to the latest version from the main branch.

2. After the composer update is complete, run the following Magento commands:

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:clean
bin/magento setup:upgrade
```

3. If you're upgrading from a significantly older version, make sure to check the changelog or release notes for any breaking changes or additional upgrade steps.

Note: If you prefer to upgrade to a specific version instead of the latest, replace `dev-main` with the desired version number in the composer command.

## Configuration

After installation, you need to configure the module with your GoPersonal credentials:

1. Log in to your Magento Admin panel.
2. Navigate to Stores > Configuration > GoPersonal > General Settings.
3. Enter your GoPersonal ClientId.
4. Save the configuration.

## Usage

Once installed and configured, the GoPersonal Magento Plugin will automatically handle the communication between your Magento store and the GoPersonal platform. This includes:

- Sending relevant customer data to GoPersonal
- Retrieving personalized content and recommendations
- Applying personalized experiences on your store frontend

For detailed usage instructions and advanced features, please refer to our [official documentation](https://academy.gopersonal.ai/).

## Support

If you encounter any issues or have questions about this module, please:

1. Check our [FAQ section](https://academy.gopersonal.ai/)
2. Contact our support team at [support@gopersonal.com](https://gopersonal.atlassian.net/servicedesk/customer/portal/2)

## Contributing

We welcome contributions to improve this module. Please submit pull requests to our GitHub repository.
