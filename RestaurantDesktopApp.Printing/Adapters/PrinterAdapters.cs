using RestaurantDesktopApp.Core.Models;
using RestaurantDesktopApp.Core.Services;
using RestaurantDesktopApp.Printing.Formatting;

namespace RestaurantDesktopApp.Printing.Adapters;

public interface IPrinterAdapter
{
    Task PrintAsync(string printerName, string payload, CancellationToken ct);
}

public sealed class WindowsDriverPrinterAdapter : IPrinterAdapter
{
    public Task PrintAsync(string printerName, string payload, CancellationToken ct)
    {
        if (string.IsNullOrWhiteSpace(printerName)) throw new InvalidOperationException("Printer not configured.");
        var outDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RestaurantDesktopApp", "spool");
        Directory.CreateDirectory(outDir);
        var file = Path.Combine(outDir, $"print_{DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}.txt");
        File.WriteAllText(file, payload);
        return Task.CompletedTask;
    }
}

public sealed class EpsonEposNetworkAdapter : IPrinterAdapter
{
    private readonly HttpClient _http;

    public EpsonEposNetworkAdapter(HttpClient http) => _http = http;

    public async Task PrintAsync(string printerEndpoint, string payload, CancellationToken ct)
    {
        using var content = new StringContent(payload);
        using var res = await _http.PostAsync(printerEndpoint, content, ct);
        res.EnsureSuccessStatusCode();
    }
}

public sealed class PrintDispatcher : IPrintDispatcher
{
    private readonly ReceiptFormatter _formatter;
    private readonly IPrinterAdapter _windowsAdapter;
    private readonly IPrinterAdapter _eposAdapter;
    private readonly AppSettings _settings;

    public PrintDispatcher(ReceiptFormatter formatter, IPrinterAdapter windowsAdapter, IPrinterAdapter eposAdapter, AppSettings settings)
    {
        _formatter = formatter;
        _windowsAdapter = windowsAdapter;
        _eposAdapter = eposAdapter;
        _settings = settings;
    }

    public async Task PrintOrderAsync(OrderItem order, int copies, CancellationToken ct)
    {
        var text = _formatter.FormatOrder(order, _settings);
        for (var i = 0; i < copies; i++)
            await ActiveAdapter().PrintAsync(Target(), text, ct);
    }

    public async Task PrintBookingAsync(BookingItem booking, int copies, CancellationToken ct)
    {
        var text = _formatter.FormatBooking(booking, _settings);
        for (var i = 0; i < copies; i++)
            await ActiveAdapter().PrintAsync(Target(), text, ct);
    }

    private IPrinterAdapter ActiveAdapter() => _settings.PrintingMode == "EpsonEposNetwork" ? _eposAdapter : _windowsAdapter;
    private string Target() => _settings.PrintingMode == "EpsonEposNetwork"
        ? $"http://{_settings.EpsonEposPrinterIp}/{_settings.EpsonEposEndpointPath ?? "cgi-bin/epos/service.cgi"}"
        : _settings.PrinterName;
}
