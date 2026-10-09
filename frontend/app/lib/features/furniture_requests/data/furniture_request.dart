import 'dart:typed_data';

const int furnitureRequestMaxAttachmentBytes = 5 * 1024 * 1024;
const Set<String> furnitureRequestAttachmentTypes = <String>{
  'image/jpeg',
  'image/png',
  'image/webp',
  'application/pdf',
};

class FurnitureRequestProductContext {
  const FurnitureRequestProductContext({
    required this.id,
    required this.name,
    this.imageUrl,
    this.imageAlt,
  });

  final String id;
  final String name;
  final String? imageUrl;
  final String? imageAlt;
}

class FurnitureRequestAttachment {
  const FurnitureRequestAttachment({
    required this.name,
    required this.bytes,
    required this.contentType,
  });

  final String name;
  final Uint8List bytes;
  final String contentType;

  int get size => bytes.lengthInBytes;

  String? get validationMessage {
    if (!furnitureRequestAttachmentTypes.contains(contentType)) {
      return 'Choose a JPEG, PNG, WebP, or PDF file.';
    }
    if (size > furnitureRequestMaxAttachmentBytes) {
      return 'Choose a file no larger than 5 MiB.';
    }
    return null;
  }
}

class FurnitureRequestDraft {
  const FurnitureRequestDraft({
    this.product,
    this.quantity = '',
    this.name = '',
    this.phone = '',
    this.email = '',
    this.length = '',
    this.width = '',
    this.height = '',
    this.material = '',
    this.color = '',
    this.notes = '',
    this.attachment,
  });

  final FurnitureRequestProductContext? product;
  final String quantity;
  final String name;
  final String phone;
  final String email;
  final String length;
  final String width;
  final String height;
  final String material;
  final String color;
  final String notes;
  final FurnitureRequestAttachment? attachment;

  FurnitureRequestDraft copyWith({
    String? quantity,
    String? name,
    String? phone,
    String? email,
    String? length,
    String? width,
    String? height,
    String? material,
    String? color,
    String? notes,
    FurnitureRequestAttachment? attachment,
    bool clearAttachment = false,
  }) => FurnitureRequestDraft(
    product: product,
    quantity: quantity ?? this.quantity,
    name: name ?? this.name,
    phone: phone ?? this.phone,
    email: email ?? this.email,
    length: length ?? this.length,
    width: width ?? this.width,
    height: height ?? this.height,
    material: material ?? this.material,
    color: color ?? this.color,
    notes: notes ?? this.notes,
    attachment: clearAttachment ? null : attachment ?? this.attachment,
  );
}

class SubmittedFurnitureRequest {
  const SubmittedFurnitureRequest({required this.id, required this.status});

  final String id;
  final String status;

  factory SubmittedFurnitureRequest.fromJson(Object? value) {
    if (value is! Map ||
        value['id'] is! String ||
        value['request_status'] is! String) {
      throw const FormatException('Invalid furniture request response.');
    }
    return SubmittedFurnitureRequest(
      id: value['id'] as String,
      status: value['request_status'] as String,
    );
  }
}

Map<String, String> validateFurnitureRequest(FurnitureRequestDraft draft) {
  final errors = <String, String>{};
  final name = draft.name.trim();
  final phone = draft.phone.trim();
  final email = draft.email.trim();
  if (name.isEmpty) errors['name'] = 'Enter your name.';
  if (name.length > 120) errors['name'] = 'Use no more than 120 characters.';
  if (phone.isEmpty && email.isEmpty) {
    errors['contact'] = 'Enter a phone number, email address, or both.';
  }
  if (phone.length > 30) errors['phone'] = 'Use no more than 30 characters.';
  if (email.isNotEmpty && (!email.contains('@') || email.length > 255)) {
    errors['email'] = 'Enter a valid email address.';
  }
  _quantityError(draft.quantity, errors);
  _dimensionErrors(draft, errors);
  _lengthError('material', draft.material, 500, errors);
  _lengthError('color', draft.color, 200, errors);
  _lengthError('notes', draft.notes, 5000, errors);
  final attachmentError = draft.attachment?.validationMessage;
  if (attachmentError != null) errors['attachment'] = attachmentError;
  return errors;
}

void _quantityError(String value, Map<String, String> errors) {
  if (value.trim().isEmpty) return;
  final quantity = int.tryParse(value);
  if (quantity == null || quantity < 1 || quantity > 100) {
    errors['quantity'] = 'Enter a whole number from 1 to 100.';
  }
}

void _dimensionErrors(FurnitureRequestDraft draft, Map<String, String> errors) {
  for (final entry in <String, String>{
    'length': draft.length,
    'width': draft.width,
    'height': draft.height,
  }.entries) {
    if (entry.value.trim().isEmpty) continue;
    final value = num.tryParse(entry.value);
    if (value == null || !value.isFinite || value <= 0 || value > 100000) {
      errors[entry.key] = 'Enter a positive measurement up to 100000 cm.';
    }
  }
}

void _lengthError(
  String field,
  String value,
  int maximum,
  Map<String, String> errors,
) {
  if (value.trim().length > maximum) {
    errors[field] = 'Use no more than $maximum characters.';
  }
}
