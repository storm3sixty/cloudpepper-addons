using System.Collections.ObjectModel;
using System.ComponentModel;
using System.Runtime.CompilerServices;
using System.Windows.Input;
using Microsoft.Extensions.Logging.Abstractions;
using RestaurantDesktopApp.Core.Models;
using RestaurantDesktopApp.Core.Persistence;
using RestaurantDesktopApp.Core.Services;
using RestaurantDesktopApp.Core.Utils;
using RestaurantDesktopApp.Printing.Adapters;
using RestaurantDesktopApp.Printing.Formatting;
using RestaurantDesktopApp.Services;

namespace RestaurantDesktopApp.ViewModels;

public sealed class MainViewModel : INotifyPropertyChanged
{
    private readonly SettingsRepository _settingsRepo;
    private readonly SyncStateRepository _stateRepo;
    private readonly SyncEngine _syncEngine;
    private readonly CancellationTokenSource _cts = new();
    private DateTimeOffset _cursor = DateTimeOffset.UtcNow.AddHours(-6);

    public event PropertyChangedEventHandler? PropertyChanged;
    public ObservableCollection<OrderItem> Orders { get; } = [];
    public ObservableCollection<BookingItem> Bookings { get; } = [];
    public ObservableCollection<string> Logs { get; } = [];
    public AppSettings Settings { get; private set; } = new();
    public string OrderSearchText { get; set; } = string.Empty;

    public ICommand SaveSettingsCommand { get; }
    public ICommand TestPrintCommand { get; }

    public MainViewModel()
    {
        var appData = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RestaurantDesktopApp");
        _settingsRepo = new SettingsRepository(appData, new DpapiSecretStore());
        _stateRepo = new SyncStateRepository(Path.Combine(appData, "state.db"));

        var http = new HttpClient();
        var notifier = new DesktopNotificationService();
        var formatter = new ReceiptFormatter();
        var printDispatcher = new PrintDispatcher(formatter, new WindowsDriverPrinterAdapter(), new EpsonEposNetworkAdapter(http), Settings);
        _syncEngine = new SyncEngine(new WooCommerceOrderSource(http, Settings), new GenericBookingSource(http, Settings), _stateRepo, notifier, printDispatcher, NullLogger.Instance);

        SaveSettingsCommand = new RelayCommand(async () => await SaveSettingsAsync());
        TestPrintCommand = new RelayCommand(async () => await TestPrintAsync());

        _ = InitializeAsync();
    }

    private async Task InitializeAsync()
    {
        Settings = await _settingsRepo.LoadAsync(_cts.Token);
        OnPropertyChanged(nameof(Settings));
        await _stateRepo.InitializeAsync(_cts.Token);
        _ = RunPollingLoopAsync();
    }

    private async Task RunPollingLoopAsync()
    {
        while (!_cts.IsCancellationRequested)
        {
            try
            {
                var result = await _syncEngine.PollAsync(Settings, _cursor, _cts.Token);
                Logs.Add($"{DateTime.Now:T} fetched: orders={result.NewOrders}, bookings={result.NewBookings}");
                _cursor = DateTimeOffset.UtcNow;
            }
            catch (Exception ex)
            {
                Logs.Add($"{DateTime.Now:T} error: {ex.Message}");
            }

            await Task.Delay(TimeSpan.FromSeconds(Math.Clamp(Settings.PollSeconds, 5, 30)), _cts.Token);
        }
    }

    private async Task SaveSettingsAsync()
    {
        await _settingsRepo.SaveAsync(Settings, _cts.Token);
        Logs.Add("Settings saved.");
    }

    private async Task TestPrintAsync()
    {
        var printer = new PrintDispatcher(new ReceiptFormatter(), new WindowsDriverPrinterAdapter(), new EpsonEposNetworkAdapter(new HttpClient()), Settings);
        await printer.PrintBookingAsync(new BookingItem
        {
            Id = 1,
            CreatedAt = DateTimeOffset.UtcNow,
            BookingTime = DateTimeOffset.UtcNow.AddHours(1),
            Name = "Test Guest",
            PartySize = 2
        }, 1, _cts.Token);
        Logs.Add("Test print sent.");
    }

    private void OnPropertyChanged([CallerMemberName] string? memberName = null) => PropertyChanged?.Invoke(this, new PropertyChangedEventArgs(memberName));
}

public sealed class RelayCommand : ICommand
{
    private readonly Func<Task> _execute;
    public RelayCommand(Func<Task> execute) => _execute = execute;
    public event EventHandler? CanExecuteChanged;
    public bool CanExecute(object? parameter) => true;
    public async void Execute(object? parameter) => await _execute();
}
