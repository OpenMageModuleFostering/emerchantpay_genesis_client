<?php
/*
 * Copyright (C) 2016 eMerchantPay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      eMerchantPay
 * @copyright   2016 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

/**
 * Class EMerchantPay_Genesis_Block_Form_Checkout
 *
 * Form Block for Checkout method
 */
class EMerchantPay_Genesis_Block_Form_Checkout extends Mage_Payment_Block_Form
{
    /**
     * Setup
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('emerchantpay/form/checkout.phtml');
    }

    /**
     * Determines if a Nominal Items were added to the cart
     * @return bool
     */
    public function getHasNominalItems()
    {
        return
            Mage::helper("emerchantpay")->getCheckoutHasRecurringItems();
    }
}
