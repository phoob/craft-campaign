<?php
/**
 * @link      https://craftcampaign.com
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\campaign\records;

use craft\db\ActiveRecord;
use DateTime;

/**
 * SendoutRecord
 *
 * @property int         $id                         ID
 * @property string      $sid                        SID
 * @property int         $campaignId                 Campaign ID
 * @property int         $senderId                   Sender ID
 * @property string      $sendoutType                Sendout type
 * @property string      $sendStatus                 Send status
 * @property string      $sendStatusMessage          Send status message
 * @property string      $sendFrom                   Send from
 * @property string      $subject                    Subject
 * @property string      $notificationEmailAddress   Notification email address
 * @property boolean     $googleAnalyticsLinkTracking Google Analytics link tracking
 * @property string      $mailingListIds             Mailing list IDs
 * @property string      $excludedMailingListIds     Excluded mailing list IDs
 * @property int         $recipients                 Recipients
 * @property int         $failedRecipients           Failed recipients
 * @property mixed       $schedule                   Schedule
 * @property string      $htmlBody                   HTML body
 * @property string      $plaintextBody              Plaintext body
 * @property DateTime    $sendDate                   Send date
 * @property DateTime    $lastSent                   Last sent
 *
 * @author    PutYourLightsOn
 * @package   Campaign
 * @since     1.0.0
 */
class SendoutRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

     /**
     * @inheritdoc
     *
     * @return string the table name
     */
    public static function tableName(): string
    {
        return '{{%campaign_sendouts}}';
    }
}
