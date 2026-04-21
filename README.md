# CS-Cart-Newsman

[Newsman](https://www.newsmanapp.com) addon for CS-Cart facilitates seamless synchronization between your CS-Cart customers/subscribers and Newsman lists/segments.

By generating an API Key within your Newsman account and installing this addon, you can effortlessly connect your shop to Newsman, enabling the synchronization of customer and subscriber data with Newsman lists and segments. This addon provides a straightforward and efficient method for integrating your CS-Cart store with Newsman.

> **Note:** Version **2.0.0** of the CS-Cart Newsman addon is not released yet — it will be published soon.

# Installation

## Manual installation (download archive and upload):
1. Download the latest **newsman.zip** archive from [releases](https://github.com/Newsman/CS-Cart-Newsman/releases) (Git tags X.Y.Z-autoload, link in the right sidebar here on GitHub). The archive **newsman.zip** contains the addon with the generated `vendor/autoload.php` which is required.
2. Go to **Admin > Add-ons > Manage add-ons**, click the **+** (plus) icon in the top-right corner and choose **Manual installation**. Upload the **newsman.zip** archive.
3. After installation, find **Newsman** in the add-on list and set its status to **Active**.
4. Go to **Admin > Marketing > Newsman** to open the addon settings page.
5. On the settings page, click the **Connect with Newsman** button and follow the steps to complete the configuration:
   - Authenticate in newsman.app.
   - Allow access to your Newsman account in your store.
   - Select the email list from the dropdown and save the settings.
6. After completing the OAuth flow, you will be redirected to the addon settings page where you can adjust all settings.
7. If there are any errors, repeat the configuration using the **Reconnect** button on the settings page. Also you can check the Newsman log viewer in the addon (log files are written under `var/newsman_logs/`). You can increase the log level from the addon configuration under the Developer section.

## Additional steps:
1. Review all settings on the Newsman configuration page for your preferred configuration.
2. Clear the CS-Cart cache from **Admin > Administration > Storage > Clear Cache**.
3. Verify the storefront for Newsman remarketing JavaScript code.
4. You can also use the debugger in **newsman.app > Integrations > NewsMAN Remarketing > "Check installation"** button. The debugger is similar to Google GTM debugger and shows if the events are tracked correctly by Newsman remarketing.

## Manual installation (create archive from source):
1. Download from GitHub repository > top right corner **Code** > Download ZIP. Unarchive the downloaded file.
2. Go to `src/app/addons/newsman/` inside the downloaded directory and run `composer install --no-dev` to install the dependencies.
3. Alternatively, use the build script: `./tools/developer/build-release-zip.sh /path/to/repo php8.2 /usr/local/bin/composer /tmp/newsman.zip`
4. Upload the resulting **newsman.zip** via **Admin > Add-ons > Manage add-ons > + > Manual installation**.

## Configuration

- [Configuration Guide (English)](https://github.com/Newsman/CS-Cart-Newsman/blob/master/configuration-en.md)
- [Ghid de Configurare (Romana)](https://github.com/Newsman/CS-Cart-Newsman/blob/master/configuration-ro.md)

# Addon Description Features

## Subscription Forms & Pop-ups
- Craft visually appealing forms and pop-ups to engage potential leads through embedded newsletter signups or exit-intent popups.
- Maintain uniformity across devices for a seamless user experience.
- Integrate forms with automations to ensure swift responses and the delivery of welcoming emails.

## Contact Lists & Segments
- Efficiently import and synchronize contact lists from diverse sources to streamline data management.
- Apply segmentation techniques to precisely target audience segments based on demographics or behavior.

## Email & SMS Marketing Campaigns
- Effortlessly send out mass campaigns, newsletters, or promotions to a broad subscriber base.
- Customize campaigns for individual subscribers by incorporating their names and suggesting relevant products.
- Re-engage subscribers by reissuing campaigns to those who haven't opened the initial email.

## Email & SMS Marketing Automation
- Automate personalized product recommendations, follow-up emails, and strategies to address cart abandonment.
- Strategically tackle cart abandonment or highlight related products to encourage completed purchases.
- Collect post-purchase feedback to gauge customer satisfaction.

## Ecommerce Remarketing
- Reconnect with subscribers through targeted offers based on past interactions.
- Personalize interactions with exclusive offers or reminders based on user behavior or preferences.

## SMTP Transactional Emails
- Ensure the timely and reliable delivery of crucial messages, such as order confirmations or shipping notifications, through SMTP.

## Extended Email and SMS Statistics
- Gain comprehensive insights into open rates, click-through rates, conversion rates, and overall campaign performance for well-informed decision-making.

The Newsman addon for CS-Cart simplifies your marketing efforts without hassle, enabling seamless communication with your audience.
