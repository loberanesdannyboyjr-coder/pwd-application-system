{ pkgs }: {
  packages = [
    pkgs.php
    pkgs.phpExtensions.pgsql
    pkgs.postgresql
    pkgs.composer
  ];
}
