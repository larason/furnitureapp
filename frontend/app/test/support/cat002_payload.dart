import 'dart:convert';
import 'dart:typed_data';

import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';

/// Builders for a frozen `CAT-002` product detail response.
///
/// Every product-detail test builds its payload here, so the model, repository,
/// controller, and widget tests exercise one identical contract shape instead of
/// each inventing its own approximation.
Map<String, Object?> cat002Product({
  String id = 'prod_01h8x9j2m4k5n6p7q8r9s0t1',
  String slug = 'modern-3-seater-fabric-sofa',
  String name = 'Modern 3-Seater Fabric Sofa',
  String description = 'Premium handcrafted living room sofa.',
  String productType = 'MADE_TO_ORDER',
  int amount = 125000000,
  String currency = 'TZS',
  Object? category = const Object(),
  Object? primaryImage = const Object(),
  Object? images = const Object(),
  Object? variants = const Object(),
  String availability = 'available',
  String stockIndicator = 'MADE_TO_ORDER',
  Object? createdAt = '2026-01-10T08:00:00Z',
  Object? updatedAt = '2026-01-12T10:00:00Z',
}) => <String, Object?>{
  'id': id,
  'name': name,
  'slug': slug,
  'description': description,
  'product_type': productType,
  'price': <String, Object?>{'amount': amount, 'currency': currency},
  'category': identical(category, const Object()) ? cat002Category() : category,
  'primary_image': identical(primaryImage, const Object())
      ? cat002Image(
          id: 'img_front',
          url: 'https://cdn.example.test/sofa-front.webp',
          altText: 'Front view',
          sortOrder: 0,
          isPrimary: true,
        )
      : primaryImage,
  'images': identical(images, const Object())
      ? <Object?>[
          cat002Image(
            id: 'img_front',
            url: 'https://cdn.example.test/sofa-front.webp',
            altText: 'Front view',
            sortOrder: 0,
            isPrimary: true,
          ),
        ]
      : images,
  'variants': identical(variants, const Object())
      ? <Object?>[
          cat002Variant(
            id: 'var_grey',
            sku: 'SOFA-MOD-3S-GRY',
            name: 'Charcoal Grey',
            amount: 125000000,
          ),
          cat002Variant(
            id: 'var_beige',
            sku: 'SOFA-MOD-3S-BEI',
            name: 'Warm Beige',
            amount: 128000000,
          ),
        ]
      : variants,
  'availability': availability,
  'stock_indicator': stockIndicator,
  'created_at': createdAt,
  'updated_at': updatedAt,
};

Map<String, Object?> cat002Category({
  String id = 'cat_01h8x8a1b2c3d4e5f6g7h8j9',
  String slug = 'living-room',
  String name = 'Living Room',
  String? description = 'Sofas, tables, and accent seating.',
}) => <String, Object?>{
  'id': id,
  'slug': slug,
  'name': name,
  'description': description,
};

Map<String, Object?> cat002Image({
  String id = 'img_front',
  String url = 'https://cdn.example.test/sofa-front.webp',
  String altText = 'Front view',
  int sortOrder = 0,
  bool isPrimary = false,
}) => <String, Object?>{
  'id': id,
  'url': url,
  'alt_text': altText,
  'sort_order': sortOrder,
  'is_primary': isPrimary,
};

Map<String, Object?> cat002Variant({
  String id = 'var_grey',
  String sku = 'SOFA-MOD-3S-GRY',
  String name = 'Charcoal Grey',
  int amount = 125000000,
  String currency = 'TZS',
  String availability = 'available',
  String stockIndicator = 'MADE_TO_ORDER',
}) => <String, Object?>{
  'id': id,
  'sku': sku,
  'name': name,
  'price': <String, Object?>{'amount': amount, 'currency': currency},
  'availability': availability,
  'stock_indicator': stockIndicator,
};

Map<String, Object?> cat002Error(int status, String code) => <String, Object?>{
  'errors': <Object?>[
    <String, Object?>{
      'code': code,
      'message': 'The requested product was not found.',
    },
  ],
  'meta': <String, Object?>{'request_id': 'req-$status'},
};

ApiClient cat002Client(Cat002Transport transport, {String? token}) => ApiClient(
  config: const AppConfig(
    environment: AppEnvironment.local,
    apiBaseUrl: 'http://127.0.0.1:8000',
  ),
  transport: transport,
  authTokenProvider: token == null ? null : _TokenProvider(token),
);

/// Records the requests a public product-detail read would make.
class Cat002Transport implements ApiTransport {
  Cat002Transport(this._handler);

  factory Cat002Transport.json(
    Map<String, Object?> payload, {
    int status = 200,
  }) => Cat002Transport(
    (_) async => ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'Content-Type': 'application/json'},
      bodyBytes: Uint8List.fromList(utf8.encode(jsonEncode(payload))),
    ),
  );

  final Future<ApiTransportResponse> Function(ApiTransportRequest) _handler;
  final List<ApiTransportRequest> requests = <ApiTransportRequest>[];
  ApiTransportRequest? get lastRequest =>
      requests.isEmpty ? null : requests.last;
  int get sendCount => requests.length;

  @override
  Future<ApiTransportResponse> send(ApiTransportRequest request) {
    requests.add(request);
    return _handler(request);
  }

  @override
  Future<void> close() async {}
}

class _TokenProvider implements AuthTokenProvider {
  _TokenProvider(this.token);

  final String token;

  @override
  Future<String?> getToken() async => token;
}
