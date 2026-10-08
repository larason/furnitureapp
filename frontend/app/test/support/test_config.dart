import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';

/// A valid LOCAL configuration for widget tests.
///
/// Widget tests exercise the theme and shell, not the validator, so they only
/// need an injected configuration that would have passed validation.
AppConfig localTestConfig({String apiBaseUrl = 'http://127.0.0.1:8000'}) =>
    AppConfig(environment: AppEnvironment.local, apiBaseUrl: apiBaseUrl);
