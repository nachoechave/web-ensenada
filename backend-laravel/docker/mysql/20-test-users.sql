CREATE DATABASE ensenada_auth_unit CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON ensenada_auth_unit.* TO 'auth_setup'@'%';
CREATE USER 'auth_runtime'@'%' IDENTIFIED BY 'synthetic-runtime-only-1234';
GRANT SELECT, INSERT, UPDATE, DELETE ON ensenada_auth_test.* TO 'auth_runtime'@'%';
