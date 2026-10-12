@description('The name of the storage account to create. The name must be between 3 and 24 characters in length and use numbers and lower-case letters only.')
@allowed([
  'prod'
  'dev'
])

param environmentName string = 'dev'
param location string = 'germanywestcentral'

