import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/app.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/config/config_validation.dart';
import 'package:sl_furnitures/theme/tokens/generated_tokens.dart';

final Matcher _throwsConfig = throwsA(isA<ConfigValidationException>());

Map<String, Object?> _local() => <String, Object?>{
  'APP_ENV': 'local',
  'API_BASE_URL': 'http://10.0.2.2:8000',
};

Map<String, Object?> _staging() => <String, Object?>{
  'APP_ENV': 'staging',
  'API_BASE_URL': 'https://staging.acmefurniture.com',
  'CLERK_PUBLISHABLE_KEY': 'pk_test_abc123',
};

Map<String, Object?> _production() => <String, Object?>{
  'APP_ENV': 'production',
  'API_BASE_URL': 'https://api.acmefurniture.com',
  'CLERK_PUBLISHABLE_KEY': 'pk_live_abc123',
};

void main() {
  group('environment selection', () {
    test('1. LOCAL parses successfully', () {
      final config = ConfigValidator.validate(_local());
      expect(config.environment, AppEnvironment.local);
      expect(config.apiBaseUrl, 'http://10.0.2.2:8000');
      expect(config.clerkPublishableKey, isNull);
      expect(config.enableDiagnostics, isFalse);
    });

    test('2. STAGING parses successfully', () {
      final config = ConfigValidator.validate(_staging());
      expect(config.environment, AppEnvironment.staging);
      expect(config.apiBaseUrl, 'https://staging.acmefurniture.com');
      expect(config.clerkPublishableKey, 'pk_test_abc123');
    });

    test('3. PRODUCTION parses successfully', () {
      final config = ConfigValidator.validate(_production());
      expect(config.environment, AppEnvironment.production);
      expect(config.apiBaseUrl, 'https://api.acmefurniture.com');
      expect(config.clerkPublishableKey, 'pk_live_abc123');
    });

    test('4. an unknown environment name is rejected', () {
      for (final name in <String>['qa', 'prod', 'development', '']) {
        expect(
          () => ConfigValidator.validate({..._local(), 'APP_ENV': name}),
          _throwsConfig,
          reason: 'APP_ENV=$name must not be accepted',
        );
      }
    });

    test('5. a missing environment selection is rejected', () {
      expect(
        () => ConfigValidator.validate({
          'API_BASE_URL': 'https://api.acmefurniture.com',
        }),
        _throwsConfig,
      );
    });

    test('20. invalid configuration never falls back to another environment', () {
      // An unrecognised name must not silently become LOCAL.
      expect(
        () => ConfigValidator.validate({..._local(), 'APP_ENV': 'localy'}),
        _throwsConfig,
      );
      // A production selection must not be downgraded because its URL is local.
      expect(
        () => ConfigValidator.validate({
          ..._production(),
          'API_BASE_URL': 'http://10.0.2.2:8000',
        }),
        _throwsConfig,
      );
      // A local-looking URL must not rescue a missing environment.
      expect(
        () =>
            ConfigValidator.validate({'API_BASE_URL': 'http://10.0.2.2:8000'}),
        _throwsConfig,
      );
    });
  });

  group('API origin validation', () {
    test('6. a missing API origin is rejected', () {
      expect(
        () => ConfigValidator.validate({'APP_ENV': 'local'}),
        _throwsConfig,
      );
    });

    test('7. a malformed API origin is rejected', () {
      for (final value in <String>['not-a-url', 'http://', '://missing-host']) {
        expect(
          () => ConfigValidator.validate({..._local(), 'API_BASE_URL': value}),
          _throwsConfig,
          reason: 'API_BASE_URL=$value must not be accepted',
        );
      }
    });

    test('8. unsupported URL schemes are rejected', () {
      for (final scheme in <String>['ftp', 'ws', 'file']) {
        expect(
          () => ConfigValidator.validate({
            ..._local(),
            'API_BASE_URL': '$scheme://api.acmefurniture.com',
          }),
          _throwsConfig,
          reason: 'scheme $scheme must not be accepted',
        );
      }
    });

    test('11. local HTTP is permitted', () {
      final config = ConfigValidator.validate({
        'APP_ENV': 'local',
        'API_BASE_URL': 'http://10.0.2.2:8000',
      });
      expect(config.apiBaseUrl, startsWith('http://'));
    });

    test('16. an API origin carrying a path prefix is rejected', () {
      for (final path in <String>['/api/v1', '/v1/', '/api']) {
        expect(
          () => ConfigValidator.validate({
            ..._production(),
            'API_BASE_URL': 'https://api.acmefurniture.com$path',
          }),
          _throwsConfig,
          reason: 'path $path must not be accepted',
        );
      }
    });

    test('17. API origin normalization is deterministic', () {
      final noisy = ConfigValidator.validate({
        ..._local(),
        'API_BASE_URL': 'HTTP://10.0.2.2:8000/',
      });
      final plain = ConfigValidator.validate({
        ..._local(),
        'API_BASE_URL': 'http://10.0.2.2:8000',
      });
      expect(noisy.apiBaseUrl, plain.apiBaseUrl);
      expect(noisy.apiBaseUrl, 'http://10.0.2.2:8000');

      // Repeating validation on identical input yields an identical value.
      final again = ConfigValidator.validate({
        ..._local(),
        'API_BASE_URL': 'HTTP://10.0.2.2:8000/',
      });
      expect(again.apiBaseUrl, noisy.apiBaseUrl);
    });

    test('userinfo, query strings, and fragments are rejected', () {
      for (final origin in <String>[
        'https://user:pass@api.acmefurniture.com',
        'https://api.acmefurniture.com?tenant=1',
        'https://api.acmefurniture.com#frag',
      ]) {
        expect(
          () => ConfigValidator.validate({
            ..._production(),
            'API_BASE_URL': origin,
          }),
          _throwsConfig,
          reason: 'origin $origin must not be accepted',
        );
      }
    });
  });

  group('transport and host rules', () {
    test('9. production HTTP is rejected', () {
      expect(
        () => ConfigValidator.validate({
          ..._production(),
          'API_BASE_URL': 'http://api.acmefurniture.com',
        }),
        _throwsConfig,
      );
    });

    test('10. staging HTTP is rejected', () {
      expect(
        () => ConfigValidator.validate({
          ..._staging(),
          'API_BASE_URL': 'http://staging.acmefurniture.com',
        }),
        _throwsConfig,
      );
    });

    test('12. production loopback hosts are rejected', () {
      for (final host in <String>['localhost', '127.0.0.1', '[::1]']) {
        expect(
          () => ConfigValidator.validate({
            ..._production(),
            'API_BASE_URL': 'https://$host',
          }),
          _throwsConfig,
          reason: 'loopback host $host must not be accepted',
        );
      }
    });

    test('13. production emulator hosts are rejected', () {
      for (final host in <String>['10.0.2.2', '10.0.3.2']) {
        expect(
          () => ConfigValidator.validate({
            ..._production(),
            'API_BASE_URL': 'https://$host',
          }),
          _throwsConfig,
          reason: 'emulator host $host must not be accepted',
        );
      }
    });

    test('14. production private-network hosts are rejected', () {
      final hosts = <String>[
        '10.1.2.3',
        '172.16.0.5',
        '172.31.255.1',
        '192.168.1.10',
        '169.254.1.1',
        'printer.local',
        'laravel.internal',
      ];
      for (final host in hosts) {
        for (final environment in <AppEnvironment>[
          AppEnvironment.production,
          AppEnvironment.staging,
        ]) {
          expect(
            () => ConfigValidator.validate(<String, Object?>{
              'APP_ENV': environment.name,
              'API_BASE_URL': 'https://$host',
              'CLERK_PUBLISHABLE_KEY': 'pk_live_abc123',
            }),
            _throwsConfig,
            reason: '$environment must reject dev host $host',
          );
        }
      }
    });

    test('15. placeholder configuration is rejected', () {
      const placeholders = <String>[
        'https://api.example.invalid',
        'https://YOUR-STAGING.example.com',
        'https://CHANGE-ME.internal.example.net',
      ];
      for (final origin in placeholders) {
        expect(
          () =>
              ConfigValidator.validate({..._staging(), 'API_BASE_URL': origin}),
          _throwsConfig,
          reason: 'placeholder $origin must not validate',
        );
      }
    });
  });

  group('Clerk public configuration', () {
    test('18. required Clerk public configuration is validated', () {
      // Required for staging and production.
      expect(
        () => ConfigValidator.validate(
          {..._staging()}..remove('CLERK_PUBLISHABLE_KEY'),
        ),
        _throwsConfig,
      );
      expect(
        () => ConfigValidator.validate({
          ..._production(),
          'CLERK_PUBLISHABLE_KEY': '',
        }),
        _throwsConfig,
      );
      // Optional locally, because no authentication flow exists yet.
      final local = ConfigValidator.validate(_local());
      expect(local.clerkPublishableKey, isNull);
      // Format is checked whenever the value is present.
      expect(
        () => ConfigValidator.validate({
          ..._local(),
          'CLERK_PUBLISHABLE_KEY': 'not-a-key',
        }),
        _throwsConfig,
      );
      expect(
        () => ConfigValidator.validate({
          ..._staging(),
          'CLERK_PUBLISHABLE_KEY': 'pk_test_XXXX',
        }),
        _throwsConfig,
        reason: 'tracked example placeholder must not validate',
      );
    });

    test('19. a secret key is never accepted as public configuration', () {
      try {
        ConfigValidator.validate({
          ..._production(),
          'CLERK_SECRET_KEY': 'sk_live_abc123',
        });
        fail('CLERK_SECRET_KEY must be rejected');
      } on ConfigValidationException catch (error) {
        expect(error.message, contains('CLERK_SECRET_KEY'));
        expect(error.message, isNot(contains('sk_live_abc123')));
      }

      expect(
        () => ConfigValidator.validate({
          ..._production(),
          'CLERK_PUBLISHABLE_KEY': 'sk_live_abc123',
        }),
        _throwsConfig,
        reason: 'a secret value must not pass as a publishable key',
      );
    });

    test('unknown fields are rejected without echoing their values', () {
      try {
        ConfigValidator.validate({
          ..._local(),
          'SOME_UNEXPECTED_FIELD': 'private-value',
        });
        fail('unknown fields must be rejected');
      } on ConfigValidationException catch (error) {
        expect(error.message, contains('Unknown configuration field'));
        expect(error.message, isNot(contains('private-value')));
      }
    });

    test('ENABLE_DIAGNOSTICS accepts booleans and rejects anything else', () {
      final enabled = ConfigValidator.validate({
        ..._local(),
        'ENABLE_DIAGNOSTICS': true,
      });
      expect(enabled.enableDiagnostics, isTrue);

      final disabled = ConfigValidator.validate(_local());
      expect(
        disabled.enableDiagnostics,
        isFalse,
        reason: 'never on by default',
      );

      expect(
        () => ConfigValidator.validate({
          ..._local(),
          'ENABLE_DIAGNOSTICS': 'maybe',
        }),
        _throwsConfig,
      );
    });
  });

  group('configuration object', () {
    test('21. configuration objects are immutable', () {
      // A const instance can only be created when every field is final, so
      // this compiles only while the value object stays immutable.
      const config = AppConfig(
        environment: AppEnvironment.production,
        apiBaseUrl: 'https://api.acmefurniture.com',
        clerkPublishableKey: 'pk_live_abc123',
        enableDiagnostics: false,
      );

      expect(config.environment, AppEnvironment.production);
      expect(config.apiBaseUrl, 'https://api.acmefurniture.com');
      expect(config.clerkPublishableKey, 'pk_live_abc123');
      expect(config.enableDiagnostics, isFalse);
      expect(identical(config, config), isTrue);
    });

    test('tracked example files behave as documented', () {
      const runnable = <String, bool>{
        'local.example.json': true,
        'local-device.example.json': true,
        'staging.example.json': false,
        'production.example.json': false,
      };

      for (final entry in runnable.entries) {
        final file = File('config/${entry.key}');
        expect(file.existsSync(), isTrue, reason: '${entry.key} must exist');

        final decoded =
            jsonDecode(file.readAsStringSync()) as Map<String, dynamic>;
        final raw = <String, Object?>{
          for (final field in decoded.entries) field.key: field.value,
        };

        if (entry.value) {
          expect(() => ConfigValidator.validate(raw), returnsNormally);
        } else {
          expect(
            () => ConfigValidator.validate(raw),
            _throwsConfig,
            reason: '${entry.key} must not be a runnable configuration',
          );
        }
      }
    });
  });

  group('startup behavior', () {
    test('a supplied compile-time secret define is rejected', () {
      if (!bool.hasEnvironment('CLERK_SECRET_KEY')) return;

      expect(
        () => ConfigValidator.loadCompileTime(),
        _throwsConfig,
        reason: 'CLERK_SECRET_KEY must never enter the application',
      );
    });

    test('22. the app runs with a validated configuration', () {
      expect(
        ConfigValidator.validate(_local()).environment,
        AppEnvironment.local,
      );
      expect(
        ConfigValidator.validate(_staging()).environment,
        AppEnvironment.staging,
      );
      expect(
        ConfigValidator.validate(_production()).environment,
        AppEnvironment.production,
      );
    });

    testWidgets('an invalid configuration stops startup with a diagnostic', (
      tester,
    ) async {
      ConfigValidationException? failure;
      try {
        ConfigValidator.validate(<String, Object?>{'APP_ENV': 'qa'});
      } on ConfigValidationException catch (error) {
        failure = error;
      }
      expect(failure, isNotNull);

      await tester.pumpWidget(ConfigFailureApp(message: failure!.message));

      expect(find.text('Configuration error'), findsOneWidget);
      expect(find.text(failure.message), findsOneWidget);
      // The screen must not leak configuration values.
      expect(find.textContaining('sk_'), findsNothing);

      final app = tester.widget<MaterialApp>(find.byType(MaterialApp));
      expect(app.theme!.useMaterial3, isTrue);
      expect(
        app.theme!.colorScheme.surface,
        GeneratedTokens.surfaceCanvas,
        reason: 'the failure screen keeps the approved brand theme',
      );
    });
  });
}
