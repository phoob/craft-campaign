<?php
/**
 * @link      https://craftcampaign.com
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\campaign\services;

use craft\web\View;
use putyourlightson\campaign\Campaign;
use putyourlightson\campaign\elements\ContactElement;
use putyourlightson\campaign\elements\MailingListElement;
use putyourlightson\campaign\models\PendingContactModel;
use putyourlightson\campaign\records\PendingContactRecord;

use Craft;
use craft\base\Component;
use craft\errors\MissingComponentException;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\mail\Message;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\helpers\Json;


/**
 * ContactsService
 *
 * @author    PutYourLightsOn
 * @package   Campaign
 * @since     1.0.0   
 */
class ContactsService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns contact by ID
     *
     * @param int $contactId
     *
     * @return ContactElement|null
     */
    public function getContactById(int $contactId)
    {
        $contact = ContactElement::find()
            ->id($contactId)
            ->status(null)
            ->one();

        return $contact;
    }

    /**
     * Returns contact by CID
     *
     * @param string $cid
     *
     * @return ContactElement|null
     */
    public function getContactByCid(string $cid)
    {
        if (!$cid) {
            return null;
        }

        $contact = ContactElement::find()
            ->where(['cid' => $cid])
            ->status(null)
            ->one();

        return $contact;
    }

    /**
     * Returns contact by email
     *
     * @param string $email
     *
     * @return ContactElement|null
     */
    public function getContactByEmail(string $email)
    {
        if (!$email) {
            return null;
        }

        $contact = ContactElement::find()
            ->email($email)
            ->status(null)
            ->one();

        return $contact;
    }

    /**
     * Saves a pending contact
     *
     * @param PendingContactModel $pendingContact
     *
     * @return bool
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function savePendingContact(PendingContactModel $pendingContact): bool
    {
        $this->purgeExpiredPendingContacts();

        $settings = Campaign::$plugin->getSettings();

        $condition = [
            'email' => $pendingContact->email,
            'mailingListId' => $pendingContact->mailingListId,
        ];

        // Check if max pending contacts reached for this email
        $numPendingContactRecords = PendingContactRecord::find()
            ->where($condition)
            ->count();

        if ($numPendingContactRecords >= $settings->maxPendingContacts) {
            // Delete oldest pending contacts
            $pendingContactRecords = PendingContactRecord::find()
                ->where($condition)
                ->orderBy(['dateCreated' => SORT_ASC])
                ->limit($numPendingContactRecords - $settings->maxPendingContacts + 1)
                ->all();

            foreach ($pendingContactRecords as $pendingContactRecord) {
                $pendingContactRecord->delete();
            }
        }

        $pendingContactRecord = new PendingContactRecord();

        $pendingContactRecord->setAttributes($pendingContact->getAttributes(), false);

        return $pendingContactRecord->save();
    }

    /**
     * Sends a verification email
     *
     * @param PendingContactModel $pendingContact
     * @param MailingListElement|null $mailingList
     *
     * @return bool
     * @throws Exception
     * @throws MissingComponentException
     * @throws \yii\base\InvalidConfigException
     */
    public function sendVerificationEmail(PendingContactModel $pendingContact, MailingListElement $mailingList = null): bool
    {
        $path = Craft::$app->getConfig()->getGeneral()->actionTrigger.'/campaign/t/verify-email';
        $url = UrlHelper::siteUrl($path, ['pid' => $pendingContact->pid]);

        $mailer = Campaign::$plugin->createMailer();

        $settings = Campaign::$plugin->getSettings();

        $subject = Craft::t('campaign', 'Verify your email address');
        $bodyText = Craft::t('campaign', 'Thank you for subscribing to the mailing list. Please verify your email address by clicking on the following link:');
        $body = $bodyText."\n".$url;

        // Get body from template if defined
        if ($mailingList !== null AND $mailingList->mailingListType->verifyEmailTemplate !== null) {
            $view = Craft::$app->getView();

            // Set template mode to site
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

            try {
                $body = $view->renderTemplate($mailingList->mailingListType->verifyEmailTemplate, [
                    'message' => $bodyText,
                    'url' => $url,
                    'mailingList' => $mailingList,
                ]);
            }
            catch (\Twig_Error_Loader $e) {}
            catch (Exception $e) {}
        }

        // Create message
        /** @var Message $message */
        $message = $mailer->compose()
            ->setFrom([$settings->defaultFromEmail => $settings->defaultFromName])
            ->setTo($pendingContact->email)
            ->setSubject($subject)
            ->setHtmlBody($body)
            ->setTextBody($body);

        return $message->send();
    }

    /**
     * Verifies a pending contact
     *
     * @param string $pid
     *
     * @return PendingContactModel|null
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function verifyPendingContact(string $pid)
    {
        // Get pending contact
        $pendingContactRecord = PendingContactRecord::find()
            ->where(['pid' => $pid])
            ->one();

        if ($pendingContactRecord === null) {
            return null;
        }

        /** @var PendingContactModel $pendingContact */
        $pendingContact = PendingContactModel::populateModel($pendingContactRecord, false);

        // Get contact if it exists
        $contact = $this->getContactByEmail($pendingContact->email);

        if ($contact === null) {
            $contact = new ContactElement();
        }

        if ($contact->verified === null) {
            $contact->verified = new \DateTime();
        }

        $contact->email = $pendingContact->email;

        // Set field values
        $contact->fieldLayoutId = Campaign::$plugin->getSettings()->contactFieldLayoutId;
        $contact->setFieldValues(Json::decode($pendingContact->fieldData));

        Craft::$app->getElements()->saveElement($contact);

        // Delete pending contact
        $pendingContactRecord = PendingContactRecord::find()
            ->where(['pid' => $pendingContact->pid])
            ->one();

        if ($pendingContactRecord !== null) {
            $pendingContactRecord->delete();
        }

        return $pendingContact;
    }

    /**
     * Deletes expired pending contacts
     *
     * @throws \Throwable
     */
    public function purgeExpiredPendingContacts()
    {
        $settings = Campaign::$plugin->getSettings();

        if ($settings->purgePendingContactsDuration === 0) {
            return;
        }

        $interval = DateTimeHelper::secondsToInterval($settings->purgePendingContactsDuration);
        $expire = DateTimeHelper::currentUTCDateTime();
        $pastTime = $expire->sub($interval);

        $pendingContactRecords = PendingContactRecord::find()
            ->where(['<', 'dateUpdated', Db::prepareDateForDb($pastTime)])
            ->all();

        foreach ($pendingContactRecords as $pendingContactRecord) {
            $pendingContactRecord->delete();

            /** @var PendingContactRecord $pendingContactRecord */
            Craft::info("Deleted pending contact {$pendingContactRecord->email}, because they took too long to verify their email.", __METHOD__);
        }
    }
}