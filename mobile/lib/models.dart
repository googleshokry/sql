double toDouble(dynamic value) => value == null ? 0 : (value is num ? value.toDouble() : double.tryParse(value.toString()) ?? 0);

class IdName {
  IdName(this.id, this.name, {this.isDefault = false});

  factory IdName.fromJson(Map<String, dynamic> json) =>
      IdName(json['id'] as int, json['name'].toString(), isDefault: json['is_default'] == true || json['is_default'] == 1);

  final int id;
  final String name;
  final bool isDefault;
}

class PaymentMethod {
  PaymentMethod(this.key, this.label);

  final String key;
  final String label;
}

class Lookups {
  Lookups({
    required this.companyName,
    required this.companyNameEn,
    required this.currency,
    required this.defaultTaxRate,
    required this.warehouses,
    required this.treasuries,
    required this.expenseCategories,
    required this.paymentMethods,
  });

  factory Lookups.fromJson(Map<String, dynamic> json) {
    List<IdName> list(String key) => (json[key] as List? ?? []).map((e) => IdName.fromJson(e as Map<String, dynamic>)).toList();
    final company = json['company'] as Map<String, dynamic>? ?? {};
    return Lookups(
      companyName: company['name']?.toString() ?? '',
      companyNameEn: company['name_en']?.toString() ?? '',
      currency: json['currency']?.toString() ?? '',
      defaultTaxRate: toDouble(json['default_tax_rate']),
      warehouses: list('warehouses'),
      treasuries: list('treasuries'),
      expenseCategories: list('expense_categories'),
      paymentMethods: (json['payment_methods'] as List? ?? [])
          .map((e) => PaymentMethod(e['key'].toString(), e['label'].toString()))
          .toList(),
    );
  }

  final String companyName;
  final String companyNameEn;
  final String currency;
  final double defaultTaxRate;
  final List<IdName> warehouses;
  final List<IdName> treasuries;
  final List<IdName> expenseCategories;
  final List<PaymentMethod> paymentMethods;

  int? get defaultWarehouseId => warehouses.isEmpty ? null : (warehouses.firstWhere((w) => w.isDefault, orElse: () => warehouses.first)).id;

  int? get defaultTreasuryId => treasuries.isEmpty ? null : (treasuries.firstWhere((t) => t.isDefault, orElse: () => treasuries.first)).id;
}

class Product {
  Product.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int,
        name = json['name'].toString(),
        sku = json['sku']?.toString() ?? '',
        barcode = json['barcode']?.toString(),
        category = json['category']?.toString(),
        unit = json['unit']?.toString(),
        price = toDouble(json['price']),
        taxRate = toDouble(json['tax_rate']),
        trackStock = json['track_stock'] == true,
        quantity = json['quantity'] == null ? null : toDouble(json['quantity']),
        minStock = toDouble(json['min_stock']),
        imageUrl = json['image_url']?.toString();

  final int id;
  final String name;
  final String sku;
  final String? barcode;
  final String? category;
  final String? unit;
  final double price;
  final double taxRate;
  final bool trackStock;
  final double? quantity;
  final double minStock;
  final String? imageUrl;

  bool get isLow => trackStock && (quantity ?? 0) <= minStock;
}

class Customer {
  Customer.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int,
        code = json['code']?.toString() ?? '',
        name = json['name'].toString(),
        phone = json['phone']?.toString() ?? json['mobile']?.toString(),
        taxNumber = json['tax_number']?.toString(),
        city = json['city']?.toString(),
        priceList = json['price_list']?.toString() ?? 'retail',
        balance = toDouble(json['balance']);

  final int id;
  final String code;
  final String name;
  final String? phone;
  final String? taxNumber;
  final String? city;
  final String priceList;
  final double balance;
}

class InvoiceItem {
  InvoiceItem.fromJson(Map<String, dynamic> json)
      : description = json['description']?.toString() ?? '',
        quantity = toDouble(json['quantity']),
        unitPrice = toDouble(json['unit_price']),
        total = toDouble(json['total']);

  final String description;
  final double quantity;
  final double unitPrice;
  final double total;
}

class Invoice {
  Invoice.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int,
        number = json['number'].toString(),
        date = json['date']?.toString() ?? '',
        customerName = (json['customer'] as Map?)?['name']?.toString(),
        status = json['status']?.toString() ?? '',
        statusLabel = json['status_label']?.toString() ?? '',
        paymentStatus = json['payment_status']?.toString() ?? '',
        paymentStatusLabel = json['payment_status_label']?.toString() ?? '',
        subtotal = toDouble(json['subtotal']),
        discount = toDouble(json['discount']),
        tax = toDouble(json['tax']),
        total = toDouble(json['total']),
        paid = toDouble(json['paid']),
        remaining = toDouble(json['remaining']),
        printUrl = json['print_url']?.toString(),
        receiptUrl = json['receipt_url']?.toString(),
        items = (json['items'] as List? ?? []).map((e) => InvoiceItem.fromJson(e as Map<String, dynamic>)).toList();

  final int id;
  final String number;
  final String date;
  final String? customerName;
  final String status;
  final String statusLabel;
  final String paymentStatus;
  final String paymentStatusLabel;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final double paid;
  final double remaining;
  final String? printUrl;
  final String? receiptUrl;
  final List<InvoiceItem> items;
}

class PaymentRecord {
  PaymentRecord.fromJson(Map<String, dynamic> json)
      : number = json['number'].toString(),
        date = json['date']?.toString() ?? '',
        amount = toDouble(json['amount']),
        party = json['party']?.toString(),
        methodLabel = json['method_label']?.toString() ?? '',
        treasury = json['treasury']?.toString(),
        invoice = json['invoice']?.toString(),
        printUrl = json['print_url']?.toString();

  final String number;
  final String date;
  final double amount;
  final String? party;
  final String methodLabel;
  final String? treasury;
  final String? invoice;
  final String? printUrl;
}

class ExpenseRecord {
  ExpenseRecord.fromJson(Map<String, dynamic> json)
      : number = json['number'].toString(),
        date = json['date']?.toString() ?? '',
        category = json['category']?.toString() ?? '',
        description = json['description']?.toString(),
        total = toDouble(json['total']);

  final String number;
  final String date;
  final String category;
  final String? description;
  final double total;
}
