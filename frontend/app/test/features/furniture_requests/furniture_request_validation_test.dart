import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/furniture_requests/data/furniture_request.dart';

void main() {
  test('requires a name and at least one contact method', () {
    final errors = validateFurnitureRequest(const FurnitureRequestDraft());

    expect(errors['name'], isNotNull);
    expect(errors['contact'], isNotNull);
  });

  test('accepts a custom request with a self-contained contact snapshot', () {
    final errors = validateFurnitureRequest(
      const FurnitureRequestDraft(
        name: 'Asha Mushi',
        email: 'asha@example.test',
      ),
    );

    expect(errors, isEmpty);
  });

  test('validates quantity, dimensions, and attachment types', () {
    final errors = validateFurnitureRequest(
      FurnitureRequestDraft(
        name: 'Asha Mushi',
        phone: '+255700000000',
        quantity: '1.5',
        length: '0',
        attachment: FurnitureRequestAttachment(
          name: 'drawing.txt',
          contentType: 'text/plain',
          bytes: Uint8List(1),
        ),
      ),
    );

    expect(
      errors.keys,
      containsAll(<String>['quantity', 'length', 'attachment']),
    );
  });

  test('mirrors the frozen REQ-001 contact formats', () {
    FurnitureRequestDraft contact(String phone, String email) =>
        FurnitureRequestDraft(name: 'Asha Mushi', phone: phone, email: email);

    expect(validateFurnitureRequest(contact('abc', ''))['phone'], isNotNull);
    expect(validateFurnitureRequest(contact('+255700000000', '')), isEmpty);
    expect(validateFurnitureRequest(contact('', 'a@b'))['email'], isNotNull);
    expect(validateFurnitureRequest(contact('', 'asha@example.test')), isEmpty);
  });

  test('permits a catalog-linked request', () {
    final draft = FurnitureRequestDraft(
      product: const FurnitureRequestProductContext(
        id: 'prod_01h8y5a1b2c3d4e5f6g7h8j9',
        name: 'Custom sofa',
      ),
      name: 'Asha Mushi',
      phone: '+255700000000',
    );

    expect(validateFurnitureRequest(draft), isEmpty);
  });

  test('accepts whitespace around numeric values', () {
    final errors = validateFurnitureRequest(
      const FurnitureRequestDraft(
        name: 'Asha Mushi',
        phone: '+255700000000',
        quantity: ' 2 ',
        length: ' 120.5 ',
        width: ' 80 ',
        height: ' 75 ',
      ),
    );

    expect(errors, isEmpty);
  });

  test('enforces the frozen 10000 cm dimension cap', () {
    final valid = validateFurnitureRequest(
      const FurnitureRequestDraft(
        name: 'Asha Mushi',
        phone: '+255700000000',
        length: '10000',
      ),
    );
    final invalid = validateFurnitureRequest(
      const FurnitureRequestDraft(
        name: 'Asha Mushi',
        phone: '+255700000000',
        length: '10000.1',
      ),
    );

    expect(valid, isEmpty);
    expect(invalid['length'], 'Enter a positive measurement up to 10000 cm.');
  });
}
