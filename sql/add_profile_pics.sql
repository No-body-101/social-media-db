-- Run this in phpMyAdmin to add profile picture and banner support
USE social_media;

ALTER TABLE user
  ADD COLUMN profile_pic VARCHAR(500) DEFAULT NULL,
  ADD COLUMN banner_pic  VARCHAR(500) DEFAULT NULL;
