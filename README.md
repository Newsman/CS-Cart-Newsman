# CS-Cart-Newsman

[Newsman](https://www.newsmanapp.com) addon for CS Cart. Sync your CS Cart customers / subscribers to [Newsman](https://www.newsmanapp.com) list / segments.
 
This is the easiest way to connect your Shop with [Newsman](https://www.newsmanapp.com). Generate an API KEY in your [Newsman](https://www.newsmanapp.com) account, install this plugin and you will be able to sync your customers and subscribers with [Newsman](https://www.newsmanapp.com) list / segments.

Installation

## Installation: 

1. Copy the *"app"* folder contents from this repository to your "app/" shop directory.
2. Install addon from Manage Add-ons and Set it as active.

![github](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/cs_cart_d.png)

Content *"app"* folder
![github](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/cs_cart_d.png)

Plugin will be actived from **Add-ons**> **Downloaded add-ons**:
![activare](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/activare.png)
![dashboard](https://github.com/Newsman/CS-Cart-Newsman/blob/master/assets/dashboard.png)

## Configuration

### Synchronization

1. Go to **Addon Manager > Newsman > Manage**
Fill in your [Newsman](https://www.newsmanapp.com) API KEY and User ID and click the Save button.

  ![General Settings](https://raw.githubusercontent.com/Newsman/CS-Cart-Newsman/master/assets/settings1.png)

2. After the [Newsman](https://www.newsmanapp.com) API KEY and User ID are set, you can choose a list and press Save.

3. Automatic synchronization is enabled for each registered customer.

### Remarketing

1. Fill in your [Newsman](https://www.newsmanapp.com) Remarketing ID, Enable Remarketing and click the Save button.
2. Clear cache from cscart admin Administration -> Storage -> Clear Cache
3. (optional, in case of 2.1 failure) Clear cache via FTP, delete cache folder from /rootcscart/var/

![Remarketing](https://raw.githubusercontent.com/Newsman/CS-Cart-Newsman/master/assets/remarketing.png)

### Sync Segmentation:

- Subscribers: email, source
- Customers with orders completed: email, firstname, lastname, city, source

## Upgrade Plugin

1. Go to **Addon Manager > Newsman > Manage**
Search for Newsman - Email Marketing -> Click Cog Bar -> Uninstall -> Disabled
  ![Upgrade](https://raw.githubusercontent.com/Newsman/CS-Cart-Newsman/master/assets/upgrade.jpg)

2. Copy new files
3. Search for Newsman - Email Marketing -> Click Cog Bar -> Install -> Activate
