<?php

/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Messaging\Notification\Email;

use CDash\Collection\BuildEmailCollection;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Messaging\Subscription\SubscriptionInterface;
use CDash\Messaging\Topic\Topic;
use CDash\Model\ActionableTypes;
use CDash\Model\BuildEmail;

class EmailBuilder
{
    protected NotificationCollection $notifications;
    protected SubscriptionCollection $subscriptions;

    public function __construct(NotificationCollection $collection)
    {
        $this->notifications = $collection;
    }

    public function createNotification(SubscriptionInterface $subscription, string $templateName): EmailMessage
    {
        $subject_template = "email.{$templateName}.subject";
        $template = "email.{$templateName}";

        $data = ['subscription' => $subscription];
        $subject = view($subject_template)->with($data);
        $body = view($template)->with($data);
        $recipient = $subscription->getSubscriber()->getAddress();
        $message = (new EmailMessage())
            ->setSubject($subject)
            ->setBody($body)
            ->setRecipient($recipient);
        // todo: this doesn't really belong here, refactor asap
        $this->setBuildEmailCollection($message, $subscription);
        return $message;
    }

    public function getNotifications(): NotificationCollection
    {
        return $this->notifications;
    }

    protected function setBuildEmailCollection(EmailMessage $message, SubscriptionInterface $subscription): void
    {
        $topics = $subscription->getTopicCollection();
        $subscriber = $subscription->getSubscriber();
        $collection = new BuildEmailCollection();

        /** @var Topic $topic */
        foreach ($topics as $topic) {
            $builds = $topic->getBuildCollection();
            $category = ActionableTypes::$categories[$topic->getTopicName()];
            $userId = $subscriber->getUserId();
            $email = $subscription->getRecipient();
            foreach ($builds as $build) {
                $buildId = $build->Id;
                $buildEmail = new BuildEmail();
                $buildEmail
                    ->SetUserId($userId)
                    ->SetBuildId($buildId)
                    ->SetEmail($email)
                    ->SetCategory($category);
                $collection->add($buildEmail);
            }
        }

        $message->setBuildEmailCollection($collection);
    }

    public function setSubscriptions(SubscriptionCollection $subscriptions): self
    {
        $this->subscriptions = $subscriptions;
        return $this;
    }

    public function getSubscriptions(): SubscriptionCollection
    {
        return $this->subscriptions;
    }
}
