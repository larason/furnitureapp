import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:marionette_flutter/marionette_flutter.dart';

import 'app.dart';
import 'config/config_validation.dart';
import 'core/network/api_client.dart';

void main() {
  final isFlutterTest = Platform.environment.containsKey('FLUTTER_TEST');
  if (kDebugMode && !isFlutterTest) {
    MarionetteBinding.ensureInitialized();
  } else {
    WidgetsFlutterBinding.ensureInitialized();
  }

  try {
    final config = ConfigValidator.loadCompileTime();
    final apiClient = ApiClient(config: config);
    if (config.enableDiagnostics) {
      debugPrint(
        'SL Furnitures configuration valid: environment=${config.environment.name}, '
        'clerkPublishableKey=${config.clerkPublishableKey == null ? 'absent' : 'present'}',
      );
    }
    runApp(
      SLFurnituresApp(
        config: config,
        apiClient: apiClient,
        ownsApiClient: true,
      ),
    );
  } on ConfigValidationException catch (error) {
    debugPrint('SL Furnitures configuration rejected: ${error.message}');
    runApp(ConfigFailureApp(message: error.message));
  }
}
