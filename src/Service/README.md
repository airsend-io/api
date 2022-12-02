# Services

Services are meant to be reusable components that can be shared across different product lines.

Services are standalone and can only depend upon other services.
Services cannot call into or allowed to depend upon other core classes..

Services typically are composed of a thin proxy library wrapper around a processor.
- proxy library abstracts the processor allowing processor changes without impacting any other business code
- proxy library deals with changes to the processor configurations, (moving to HA etc) or becoming a microservice without any impact 