<?php

namespace Tygh\Addons\Newsman\Remarketing;

use Tygh\Addons\Newsman\Config;

class Renderer
{
    /** @var Config */
    protected $config;

    /** @var TrackingScript */
    protected $trackingScript;

    /** @var CartTracking */
    protected $cartTracking;

    /** @var CartTrackingNative */
    protected $cartTrackingNative;

    /** @var CustomerIdentify */
    protected $customerIdentify;

    /** @var CustomerIdentifyDeferred */
    protected $customerIdentifyDeferred;

    /** @var ProductView */
    protected $productView;

    /** @var CategoryView */
    protected $categoryView;

    /** @var PageView */
    protected $pageView;

    /** @var Purchase */
    protected $purchase;

    public function __construct(
        Config $config,
        TrackingScript $trackingScript,
        CartTracking $cartTracking,
        CartTrackingNative $cartTrackingNative,
        CustomerIdentify $customerIdentify,
        CustomerIdentifyDeferred $customerIdentifyDeferred,
        ProductView $productView,
        CategoryView $categoryView,
        PageView $pageView,
        Purchase $purchase
    ) {
        $this->config = $config;
        $this->trackingScript = $trackingScript;
        $this->cartTracking = $cartTracking;
        $this->cartTrackingNative = $cartTrackingNative;
        $this->customerIdentify = $customerIdentify;
        $this->customerIdentifyDeferred = $customerIdentifyDeferred;
        $this->productView = $productView;
        $this->categoryView = $categoryView;
        $this->pageView = $pageView;
        $this->purchase = $purchase;
    }

    /**
     * @param string $currencyCode
     * @return string
     */
    public function renderTrackingScript($currencyCode = '')
    {
        return $this->trackingScript->getHtml($currencyCode);
    }

    /**
     * @param string $cartUrl
     * @param bool   $isCheckoutComplete
     * @return string
     */
    public function renderCartTracking($cartUrl, $isCheckoutComplete = false)
    {
        if ($this->config->isThemeCartCompatibility()) {
            return $this->cartTracking->getHtml($cartUrl, $isCheckoutComplete);
        }

        return $this->cartTrackingNative->getHtml();
    }

    /**
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @return string
     */
    public function renderCustomerIdentify($email, $firstname = '', $lastname = '')
    {
        return $this->customerIdentify->getHtml($email, $firstname, $lastname);
    }

    /**
     * @param string $identifyUrl
     * @return string
     */
    public function renderCustomerIdentifyDeferred($identifyUrl)
    {
        return $this->customerIdentifyDeferred->getHtml($identifyUrl);
    }

    /**
     * @param int    $productId
     * @param string $productName
     * @param string $category
     * @param string $price
     * @return string
     */
    public function renderProductView($productId, $productName = '', $category = '', $price = '')
    {
        return $this->productView->getHtml($productId, $productName, $category, $price);
    }

    /**
     * @param int   $categoryId
     * @param array $products
     * @param string $listName
     * @return string
     */
    public function renderCategoryView($categoryId, $products = array(), $listName = '')
    {
        return $this->categoryView->getHtml($categoryId, $products, $listName);
    }

    /**
     * @return string
     */
    public function renderPageView()
    {
        return $this->pageView->getHtml();
    }

    /**
     * @param array $orderInfo
     * @return string
     */
    public function renderPurchase($orderInfo)
    {
        return $this->purchase->getHtml($orderInfo);
    }
}
