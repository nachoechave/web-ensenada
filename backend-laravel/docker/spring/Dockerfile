FROM maven:3.9.16-eclipse-temurin-21-alpine AS build
WORKDIR /app
COPY --from=spring pom.xml ./
COPY --from=spring src/main ./src/main
RUN mvn -B -q package -Dmaven.test.skip=true

FROM eclipse-temurin:21-jre-alpine
WORKDIR /app
COPY --from=build /app/target/*.jar app.jar
ENTRYPOINT ["java", "-jar", "app.jar"]
