<?php

namespace tests\acceptance;

use AcceptanceTester;
use Codeception\Module\Yii2;
use Craft;

class LoginCest
{
    public function login(AcceptanceTester $I)
    {
        /** @var Yii2 $I */
        $I->amOnPage('index.php');
        echo $I->grabPageSource();die();
        $I->sendAjaxPostRequest('index.php?p=admin/actions/users/login', [
            'loginName' => 'ben',
            'password' => 'HjFjpibFAzbxmCpLgTmBzqM3',
        ]);

        Craft::$app->getSession()->set('enableDebugToolbarForCp', false);

        $I->amOnPage('index.php?p=admin/settings');
        echo $I->grabPageSource();die();
        $I->seeInTitle('General Settings');

        $I->fillField('username', 'ben');
        $I->fillField('password', 'HjFjpibFAzbxmCpLgTmBzqM3');
        $I->click('#submit');
        $I->see('Dashboard');
    }
}
