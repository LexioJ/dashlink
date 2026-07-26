# Makefile for DashLink Nextcloud app

app_name=dashlink
project_dir=$(CURDIR)
build_dir=$(project_dir)/build
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=1.0.0

all: dev

# Development build
.PHONY: dev
dev:
	npm run dev

# Production build
.PHONY: build
build:
	npm run build

# Install composer dependencies
.PHONY: composer
composer:
	composer install --prefer-dist --no-dev
	composer dump-autoload --no-dev

# Install npm dependencies
.PHONY: npm
npm:
	npm install --legacy-peer-deps

# Clean build artifacts
.PHONY: clean
clean:
	rm -rf $(build_dir)
	rm -rf node_modules
	rm -rf vendor
	rm -rf js/

# Run tests
.PHONY: test
test:
	composer test

# Run linters
.PHONY: lint
lint:
	npm run lint
	composer run lint

# Fix code style
.PHONY: fix
fix:
	npm run lint:fix
	composer run cs:fix

# Build app package for app store
.PHONY: appstore
appstore: clean composer build
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/.git \
	--exclude=/.github \
	--exclude=/build \
	--exclude=/docs \
	--exclude=/l10n/templates \
	--exclude=/node_modules \
	--exclude=/src \
	--exclude=/tests \
	--exclude=/.gitignore \
	--exclude=/.tx \
	--exclude=/phpunit.xml \
	--exclude=/composer.json \
	--exclude=/composer.lock \
	--exclude=/package.json \
	--exclude=/package-lock.json \
	--exclude=/webpack.config.js \
	--exclude=/Makefile \
	--exclude=/.editorconfig \
	--exclude=/CLAUDE.md \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package..."; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi

.PHONY: help
help:
	@echo "Available targets:"
	@echo "  make dev        - Build for development with watch mode"
	@echo "  make build      - Build for production"
	@echo "  make composer   - Install PHP dependencies (production only)"
	@echo "  make npm        - Install Node.js dependencies"
	@echo "  make clean      - Clean build artifacts"
	@echo "  make test       - Run tests"
	@echo "  make lint       - Run linters"
	@echo "  make fix        - Fix code style issues"
	@echo "  make appstore   - Build app package for app store (includes vendor)"
