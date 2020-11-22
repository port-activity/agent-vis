# Port Activity App / VIS integration

## Description
API for VIS requests and VIS service polling

## Configuring container
Copy .env.template to .env and fill values

## Configuring local development environment
Copy src/lib/init_local.php.sample.php to src/lib/init_local.php and fill values

## Commands for docker compose
- `docker-compose build` build containers
- `docker-compose up -d` start containers in detached mode
- `docker-compose stop` stop containers

## Temporary web UI for testing
- With browser: http://localhost:8888
- Username and password as in Dockerfile

## Polling and saving VIS notifications and messages
- Configure container and/or local development environment
- curl http://localhost:8888/api.php/poll-save

## Using API
- Configure container and/or local development environment
- API usage example:
```curl http://localhost:8888/api.php/find-services/service-id:urn:mrn:stm:service:instance:sma:unikie:testship```

```
curl http://localhost:8888/api.php/upload-text-message \
-X POST \
--data-binary '{"to_service_id":"urn:mrn:stm:service:instance:sma:unikie:testship", "to_url":"https://smavistest.stmvalidation.eu/UNIKIE03","author":"Author","subject":"Subject","body":"Body"}'
```

```
curl http://localhost:8888/api.php/send-rta \
-X POST \
--data-binary '{"to_service_id":"urn:mrn:stm:service:instance:sma:unikie:testship", "to_url":"https://smavistest.stmvalidation.eu/UNIKIE03",
"rtz_parse_results":"{\"status\":3,\"waypointId\":10,\"eta\":\"2020-03-04T12:00:00Z\"}",
"rtz":"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<route xmlns:stm=\"http:\/\/stmvalidation.eu\/STM\/1\/0\/0\" xmlns:xsi=\"http:\/\/www.w3.org\/2001\/XMLSchema-instance\" version=\"1.1\" xmlns=\"http:\/\/www.cirm.org\/RTZ\/1\/1\">\n  <routeInfo routeName=\"route\" routeAuthor=\"author\" vesselName=\"Vessel Name\" vesselIMO=\"1234567\" vesselVoyage=\"urn:mrn:stm:voyage:id:test:2d1bb3f4-2c7b-42f0-8967-2601cceb840e\">\n    <extensions>\n      <extension xsi:type=\"stm:RouteInfoExtension\" manufacturer=\"STM\" name=\"routeInfoEx\" version=\"1.0.0\" routeStatusEnum=\"7\" routeVersion=\"1\" \/>\n    <\/extensions>\n  <\/routeInfo>\n  <waypoints>\n    <defaultWaypoint radius=\"0.1\">\n      <leg starboardXTD=\"0.03\" portsideXTD=\"0.03\" safetyContour=\"30\" geometryType=\"Loxodrome\" \/>\n    <\/defaultWaypoint>\n    <waypoint id=\"10\" name=\"Waypoint 10\" radius=\"0.3\">\n      <position lat=\"60.69238805\" lon=\"17.23492497\" \/>\n      <leg starboardXTD=\"0.2\" portsideXTD=\"0.2\" geometryType=\"Loxodrome\" \/>\n    <\/waypoint>\n    <waypoint id=\"11\" name=\"Waypoint 11\" radius=\"0.3\">\n      <position lat=\"60.69238805\" lon=\"17.23492497\" \/>\n      <leg starboardXTD=\"0.2\" portsideXTD=\"0.2\" geometryType=\"Loxodrome\" \/>\n    <\/waypoint>\n  <\/waypoints>\n  <schedules>\n    <schedule id=\"1\" name=\"Schedule1\">\n      <calculated>\n        <scheduleElement waypointId=\"10\" speed=\"24.9999787843511\" \/>\n      <\/calculated>\n    <\/schedule>\n  <\/schedules>\n<\/route>",
"rta":"2020-03-04T13:00:00Z","eta_min":"2020-03-04T12:30:00Z","eta_max":"2020-03-04T13:30:00Z"}'
```