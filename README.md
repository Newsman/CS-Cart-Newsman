# CS-Cart-Newsman

[Newsman](https://www.newsmanapp.com) addon for CS Cart facilitates seamless synchronization between your CS Cart customers/subscribers and NewsMAN lists/segments.
 
By generating an API KEY within your NewsMAN account and installing this plugin, you can effortlessly connect your shop to NewsMAN, enabling the synchronization of customer and subscriber data with NewsMAN lists and segments. This plugin provides a straightforward and efficient method for integrating your CS Cart store with NewsMAN

Installation

## Installation: 

1. Copy the "app" folder contents from this repository to your "app/" shop directory.
2. Install addon from Manage Add-ons and Set it as active.

  ![github](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/cs_cart_d.png)


  Content "app" folder

  ![github](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/app_content_new.png)

  Plugin will be actived from Add-ons> Downloaded add-ons:
  ![activare](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/activare.png)
  ![dashboard](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/dashboard.png)

## Configuration

### Synchronization

1. The process is automated, login with NewsMAN via Oauth and the settings will get automatically filled based on your selection

![image](https://raw.githubusercontent.com/Newsman/OpenCart-Newsman/master/assets/oauth1.png)
![image](https://raw.githubusercontent.com/Newsman/OpenCart-Newsman/master/assets/oauth2.png)

2. Go to Addon Manager > NewsMAN > Manage Fill in your NewsMAN API KEY and User ID and click the Save button.

  ![General Settings](https://raw.githubusercontent.com/Newsman/CS-Cart-Newsman/master/assets/settings1.png)

3. After the NewsMAN API KEY and User ID are set, you can choose a list and press Save.
4. Automatic synchronization is enabled for each registered customer.


### Remarketing

1. Fill in your NewsMAN Remarketing ID, Enable Remarketing and click the Save button.
2. Clear cache from cscart admin Administration -> Storage -> Clear Cache
3. (optional, in case of 2.1 failure) Clear cache via FTP, delete cache folder from /rootcscart/var/

![Remarketing](https://raw.githubusercontent.com/Newsman/CS-Cart-Newsman/master/assets/remarketing.png)

### Sync Segmentation:

- Subscribers: email, source
- Customers with orders completed: email, firstname, lastname, city, source

## Upgrade Plugin

1. Go to Addon Manager > NewsMAN > Manage Search for NewsMAN - Email Marketing -> Click Cog Bar -> Uninstall -> Disabled 
  ![Upgrade](https://raw.githubusercontent.com/Newsman/CS-Cart-Newsman/master/assets/upgrade.jpg)

2. Copy new files
3. Search for Newsman - Email Marketing -> Click Cog Bar -> Install -> Activate

Description 

Subscription Forms & Pop-ups<br>
Craft visually appealing forms and pop-ups to capture potential leads, incorporating embedded newsletter signups or exit-intent popups.
Maintain form consistency across various devices to provide a smooth and seamless user experience.
Integrate forms with automated processes for swift responses and personalized welcome emails.

Contact Lists & Segments<br>
Effortlessly import and synchronize contact lists from diverse sources for simplified data management.
Utilize segmentation strategies to target specific audience segments based on demographics or behavior.

Email & SMS Marketing Campaigns<br>
Effortlessly dispatch mass campaigns, newsletters, or promotions to a broad subscriber base.
Tailor campaigns for individual subscribers, addressing them by name and recommending pertinent products.
Re-engage subscribers by re-sending campaigns to those who haven't opened the initial email.

Email & SMS Marketing Automation<br>
Automate personalized product recommendations, follow-up emails, and strategies for addressing cart abandonment.
Strategically tackle cart abandonment or showcase related products to encourage the completion of purchases.
Collect post-purchase feedback to enhance customer satisfaction.

Ecommerce Remarketing<br>
Reconnect with subscribers through targeted offers grounded in past interactions.
Personalize interactions with exclusive offers or reminders based on user behavior or preferences.

SMTP Transactional Emails<br>
Ensure the prompt and dependable delivery of crucial messages, such as order confirmations or shipping notifications, via SMTP.

Extended Email and SMS Statistics<br>
Obtain insights into open rates, click-through rates, conversion rates, and overall campaign performance to make well-informed decisions.
The NewsMAN Plugin for CsCart streamlines your marketing endeavors effortlessly, simplifying the process of effectively connecting with your audience.

