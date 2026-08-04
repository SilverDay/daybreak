-- Add 'teams' to the webhook payload format enum.
-- Microsoft Teams incoming webhooks (Power Automate workflows) require an Adaptive
-- Card payload — neither the Slack, Discord, nor generic JSON shapes satisfy that,
-- which is why users picking any of those three saw a "missing card" delivery error.
SET NAMES utf8mb4;

ALTER TABLE user_webhooks
  MODIFY COLUMN format ENUM('slack','discord','teams','generic') NOT NULL DEFAULT 'generic';
