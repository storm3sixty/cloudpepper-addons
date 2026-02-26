using System.Text.Json;
using RestaurantDesktopApp.Core.Models;
using RestaurantDesktopApp.Core.Utils;

namespace RestaurantDesktopApp.Core.Persistence;

public sealed class SettingsRepository
{
    private readonly string _path;
    private readonly ISecretStore _secretStore;

    public SettingsRepository(string appDataPath, ISecretStore secretStore)
    {
        Directory.CreateDirectory(appDataPath);
        _path = Path.Combine(appDataPath, "settings.json");
        _secretStore = secretStore;
    }

    public async Task<AppSettings> LoadAsync(CancellationToken ct = default)
    {
        if (!File.Exists(_path)) return new AppSettings();
        await using var stream = File.OpenRead(_path);
        var settings = await JsonSerializer.DeserializeAsync<AppSettings>(stream, cancellationToken: ct) ?? new AppSettings();
        settings.PasswordOrConsumerSecret = SafeUnprotect(settings.PasswordOrConsumerSecret);
        return settings;
    }

    public async Task SaveAsync(AppSettings settings, CancellationToken ct = default)
    {
        var copy = new AppSettings
        {
            BaseUrl = settings.BaseUrl,
            AuthMode = settings.AuthMode,
            UsernameOrConsumerKey = settings.UsernameOrConsumerKey,
            PasswordOrConsumerSecret = _secretStore.Protect(settings.PasswordOrConsumerSecret),
            PollSeconds = settings.PollSeconds,
            PrinterName = settings.PrinterName,
            PaperWidth = settings.PaperWidth,
            OrderCopies = settings.OrderCopies,
            BookingCopies = settings.BookingCopies,
            EnableSound = settings.EnableSound,
            SoundPath = settings.SoundPath,
            AutoPrintOrders = settings.AutoPrintOrders,
            AutoPrintBookings = settings.AutoPrintBookings,
            RestaurantHeader = settings.RestaurantHeader,
            PrintingMode = settings.PrintingMode,
            EpsonEposPrinterIp = settings.EpsonEposPrinterIp,
            EpsonEposEndpointPath = settings.EpsonEposEndpointPath
        };

        await using var stream = File.Create(_path);
        await JsonSerializer.SerializeAsync(stream, copy, new JsonSerializerOptions { WriteIndented = true }, ct);
    }

    private string SafeUnprotect(string value)
    {
        try { return _secretStore.Unprotect(value); }
        catch { return string.Empty; }
    }
}
