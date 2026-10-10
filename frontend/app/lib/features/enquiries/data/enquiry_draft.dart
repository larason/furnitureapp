import '../../../core/attachments/pending_attachment.dart';

/// Frozen ENQ-001 field bounds.
///
/// Laravel is authoritative; these bounds exist so the customer sees a problem
/// before a round trip and so the form never renders a field the contract
/// cannot accept.
const int enquiryNameMaxLength = 120;
const int enquiryPhoneMaxLength = 30;
const int enquiryEmailMaxLength = 255;
const int enquirySubjectMinLength = 5;
const int enquirySubjectMaxLength = 200;
const int enquiryMessageMinLength = 10;
const int enquiryMessageMaxLength = 5000;

final RegExp _emailPattern = RegExp(r"^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$");

final RegExp _phonePattern = RegExp(r'^\+?[0-9][0-9 ().-]{6,29}$');

/// A catalog product an enquiry is about.
///
/// [id] is always the server-returned opaque product identifier; a display
/// name is never used to derive it.
class EnquiryProductContext {
  const EnquiryProductContext({
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

/// The customer-authored ENQ-001 submission snapshot.
///
/// Values are held exactly as typed; every value sent to the API is trimmed by
/// the repository. The draft never carries a user identity: `user_id` is
/// server-derived and must never appear here.
class EnquiryDraft {
  const EnquiryDraft({
    this.subject = '',
    this.message = '',
    this.name = '',
    this.phone = '',
    this.email = '',
    this.product,
    this.attachment,
  });

  final String subject;
  final String message;
  final String name;
  final String phone;
  final String email;
  final EnquiryProductContext? product;
  final PendingAttachment? attachment;

  EnquiryDraft copyWith({
    String? subject,
    String? message,
    String? name,
    String? phone,
    String? email,
    EnquiryProductContext? product,
    PendingAttachment? attachment,
    bool clearAttachment = false,
  }) => EnquiryDraft(
    subject: subject ?? this.subject,
    message: message ?? this.message,
    name: name ?? this.name,
    phone: phone ?? this.phone,
    email: email ?? this.email,
    product: product ?? this.product,
    attachment: clearAttachment ? null : attachment ?? this.attachment,
  );
}

/// The confirmed ENQ-001 creation result.
///
/// Only documented response fields are decoded. `id` is an opaque public
/// reference, not a credential, and no private endpoint is reachable with it.
class SubmittedEnquiry {
  const SubmittedEnquiry({required this.id, required this.status});

  final String id;
  final String status;

  factory SubmittedEnquiry.fromJson(Object? value) {
    if (value is! Map ||
        value['id'] is! String ||
        value['enquiry_status'] is! String) {
      throw const FormatException('Invalid enquiry response.');
    }
    return SubmittedEnquiry(
      id: value['id'] as String,
      status: value['enquiry_status'] as String,
    );
  }
}

/// Advisory ENQ-001 validation for the form.
///
/// [authenticated] selects the contract's actor-aware contact rule: an
/// anonymous visitor must supply a name and a reachable phone or email, while
/// an authenticated customer may omit contact because Laravel derives it from
/// the verified account.
Map<String, String> validateEnquiry(
  EnquiryDraft draft, {
  required bool authenticated,
}) {
  final errors = <String, String>{};
  final subject = draft.subject.trim();
  final message = draft.message.trim();
  final name = draft.name.trim();
  final phone = draft.phone.trim();
  final email = draft.email.trim();
  if (subject.isEmpty) {
    errors['subject'] = 'Enter a subject.';
  } else if (subject.length < enquirySubjectMinLength ||
      subject.length > enquirySubjectMaxLength) {
    errors['subject'] =
        'Use between $enquirySubjectMinLength and '
        '$enquirySubjectMaxLength characters.';
  }
  if (message.isEmpty) {
    errors['message'] = 'Enter a message.';
  } else if (message.length < enquiryMessageMinLength ||
      message.length > enquiryMessageMaxLength) {
    errors['message'] =
        'Use between $enquiryMessageMinLength and '
        '$enquiryMessageMaxLength characters.';
  }
  if (!authenticated && name.isEmpty) {
    errors['name'] = 'Enter your name.';
  }
  if (!authenticated && phone.isEmpty && email.isEmpty) {
    errors['contact'] = 'Enter a phone number, email address, or both.';
  }
  if (name.length > enquiryNameMaxLength) {
    errors['name'] = 'Use no more than $enquiryNameMaxLength characters.';
  }
  if (phone.length > enquiryPhoneMaxLength ||
      (phone.isNotEmpty && !_phonePattern.hasMatch(phone))) {
    errors['phone'] = 'Enter a valid phone number.';
  }
  if (email.length > enquiryEmailMaxLength ||
      (email.isNotEmpty && !_emailPattern.hasMatch(email))) {
    errors['email'] = 'Enter a valid email address.';
  }
  final attachmentError = draft.attachment?.validationMessage;
  if (attachmentError != null) errors['attachment'] = attachmentError;
  return errors;
}
