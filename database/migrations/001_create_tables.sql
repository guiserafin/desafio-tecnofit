CREATE TABLE `user` (
    `id`   INT          NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
);

CREATE TABLE `movement` (
    `id`   INT          NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_movement_name` (`name`)  -- busca case-insensitive por nome
);

CREATE TABLE `personal_record` (
    `id`          INT      NOT NULL AUTO_INCREMENT,
    `user_id`     INT      NOT NULL,
    `movement_id` INT      NOT NULL,
    `value`       FLOAT    NOT NULL,
    `date`        DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_pr_movement_user` (`movement_id`, `user_id`),  -- cobertura para query de ranking
    CONSTRAINT `fk_pr_user`     FOREIGN KEY (`user_id`)     REFERENCES `user`(`id`),
    CONSTRAINT `fk_pr_movement` FOREIGN KEY (`movement_id`) REFERENCES `movement`(`id`)
);