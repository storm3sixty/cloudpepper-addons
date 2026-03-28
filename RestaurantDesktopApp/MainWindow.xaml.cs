using RestaurantDesktopApp.ViewModels;

namespace RestaurantDesktopApp;

public partial class MainWindow : Window
{
    public MainWindow() => InitializeComponent();

    private void SecretBox_OnPasswordChanged(object sender, RoutedEventArgs e)
    {
        if (DataContext is MainViewModel vm && sender is PasswordBox pb)
            vm.Settings.PasswordOrConsumerSecret = pb.Password;
    }
}
