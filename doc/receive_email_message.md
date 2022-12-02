# Airsend - Receiving messages through email

This document describes the message receiving when a user respond to a email notification from a channel (uread
 messages or mentions).
 
## High-level flow

```
         |
         |  Customer reply to a notification email, typing a response message
         V
| Client Mail Server |
         |
         |  Sends the email to our domain address. DNS MX record points the delivery to AWS SES
         V
    | AWS SES |
         |
         |  Sends the email content to an AWS SNS topic, following a receiving rule defined on SES
         V
    | AWS SNS |
         |
         |  Sends the content to `email.receive` endpoint, that is set as a subscriber to the topic
         |
-- Airsend Boundary -- From now, all the interaction is inside Arisend
         | 
         V
  | Airsend API | (email.receive)
         |
         |  Publish the email text/plain text inside an event on a Kafka topic (after some verifications)
         V
     | Kafka |
         |
         |  The event is consumed by the Nodeutil kafka consumer (subscribed to the topic)
         V
 | Airsend Nodeutil |
         |
         |  Publish an event with the message typed by the user (quotations and signatures removed)
         V
     | Kafka |
         |
         |  The event is consumed by the Kafkaworker
         V
   | Kafkaworker |
         |
         |  Kafkaworker calls the bgproccess endpoint
         V
   | Airsend API | (bgprocess)
         |
         | The message is finally posted to the channel
         V
    | Channel |
         
```

## Detailed design

Here we specify the details of each piece of the flow.

### AWS SES (Simple Email Service)

SES is able to receive emails when the domain DNS MX record is properly configured for that. We basically point the
 MX record of the domain to SES address, and all emails sent to this domain are processed by SES.
 
To control the processing of those emails, we have to create rules inside the SES console. For this feature, we
included a catch-all rule on SES, that publishes all received emails as events on a SNS topic (see below). The event
includes some sending info (from SMTP protocol), and the raw email text. So far, we're receiving all emails sent to
the Airsend Mailer domain, and publishing them on SNS.
 
### AWS SNS (Simple Notification Service)

SNS is a simple pub/sub queue structure, used to handle notifications. We have a specific queue to receive the emails
from SES, and we have a subscription configured to this queue, that reads the event, and sends it's payload to an
http endpoint as a POST request. This subscription is set to POST the event payload to an Airsend API endpoint
(email.receive).
 
### Airsend API - email.receive

This is the entrance gateway for email messages on Airsend. This endpoint was build to be flexible, and allow easy
interchange between email services (right now we use AWS, but we already tried Mailgun, and we can switch it to
 another service in the future).

So, the first thing that we do on this endpoint, is to get the posted request body, and pass it to the mailer service,
through the `parseReceivedMessage` method (that any mailer service driver must implement), that returns a tuple of 3
values:
* Sender (string) - The raw email address of the sender, without any display name.
* Recipients (string[]) - The raw email addresses of the recipients, without any display name.
* Raw email (string) - The raw content of the email message, including headers and body

It's up to the mailer service implementation to take care of this filtering.

After that, we do a bunch of checks:

1. Check if there is, among the recipients, a valid channel email (halt on failure)
1. Try to extract the read only token from the recipient address (the tag after the + sign)
1. If a token wasn't found, try to find it on the email body (halt on failure)
1. Check if the found token exist (halt on failure)
1. Check if the token belongs to the identified channel (halt on failure)
1. Check if the token is still valid/not expired (halt on failure)
1. Check if the sender address belong to a valid user (halt on failure)
1. Check if the found user is a member of the found channel (halt on failure)

If everything went well, we extract the `plain/text` part of the email (if there is no plain text part on the email
, we halt).

Now we have all the checks done, and the plain text body of the email extracted, the message will be posted to the
channel. But we still have a problem to solve: The plain text message still have quotations (from the emails that the
 user replied to) and possible an email signature. We don't want any of those things been included on the message
  that will be posted to the channel.
  
We couldn't find a good php library to filter this kind of message (remove quotations and signature). But we found
Node libraries that do the job, so we'll publish an event to the nodeutil topic (Kafka), with the command 
`strip_message_from_email`, and a payload containing the plain text, the user id and the channel id that we found.

IMPORTANT: purposely this endpoint always return a 204 success response (No Content), without a body. The reasons are:
1. Nobody is checking this response, so there's no point in returning any detail if the message was rejected. The
 details are logged.
1. We don't want to give any information to potential attackers. 

### Airsend NodeUtil

The nodeutil app will receive this event inside the `kafkaconsumer`, detect the command, and clean up the message.

For that we use two Node third party packages:

* Talon - It's a NodeJS port from mailgun/talon (written originally in Python). The package only supports signature
 removing, and that's the feature that we used here.
* Planer - It's a library that remove quotations from emails. We only used the plain text removing. It works for the
 most of the email quotation patters (there isn't an universal pattern).
 
Those libraries should work in 99% of the cases.

Once we have the message cleaned up, we're ready to post it inside the channel. To do that, we again publish a event
on a kafka topic (with the command `post_email_response_message`, including the user id, channel id and the cleaned
 message) through the kafka producer. This time the event will be catch by the KafkaWorker process (that is
  automatically subscribed to the topic).

### Kafka Worker

On this implementation, we didn't touched kafka worker, it just kept doing what they did before, receiving events and
 posting then to the `bgprocess` api endpoint.
 
### Airsend API - bgprocess

The bgprocess endpoint parses the command, and basically post the message to the channel, using the channel id, user
 id and message (clean message) included on the event payload. This endpoint is protected agains external access, so
  we don't care about security here, we just post the message.



