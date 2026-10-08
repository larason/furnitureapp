import 'package:flutter/material.dart';

import 'app.dart';
import 'config/config_validation.dart';

void main() {
  try {
    final config = ConfigValidator.loadCompileTime();
    if (config.enableDiagnostics) {
      debugPrint(
        'SL Furnitures configuration valid: environment=${config.environment.name}, '
        'clerkPublishableKey=${config.clerkPublishableKey == null ? 'absent' : 'present'}',
      );
    }
    runApp(SLFurnituresApp(config: config));
  } on ConfigValidationException catch (error) {
    debugPrint('SL Furnitures configuration rejected: ${error.message}');
    runApp(ConfigFailureApp(message: error.message));
  }
}
